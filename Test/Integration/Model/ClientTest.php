<?php
/**
 * Taxdoo_VAT
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Taxdoo
 * @package    Taxdoo_VAT
 * @copyright  Copyright (c) 2021 Andrea Lazzaretti.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

// @codingStandardsIgnoreStart
namespace Taxdoo\VAT\Test\Integration\Model;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Exception\LocalizedException;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @see https://app.hiptest.com/projects/69435/test-plan/folders/419534/scenarios/2587535
 */
class ClientTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @var \Taxdoo\VAT\Model\Client
   */
  protected $taxdooClient;

  /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
  protected $scopeConfig;

  private $transactionsToBeCleaned;

  protected function setUp(): void
  {
      $this->scopeConfig = Bootstrap::getObjectManager()->get('Magento\Framework\App\Config\ScopeConfigInterface');
      $taxdooCredentials = require_once __DIR__ . '/../credentials.php';
      $this->_setConfig($taxdooCredentials);

      $this->taxdooClient = Bootstrap::getObjectManager()->get('Taxdoo\VAT\Model\Client');
      $this->taxdooClient->setApiKey($this->scopeConfig->getValue(
          TaxdooConfig::TAXDOO_APIKEY,
          ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
      ));
      $this->transactionsToBeCleaned = [];
  }

  public function testAccount()
  {
      $response = $this->taxdooClient->checkApiKey();

      $taxdooEmail = $this->scopeConfig->getValue(
          TaxdooConfig::TAXDOO_EMAIL,
          ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
      );

      $this->assertEquals($taxdooEmail, $response['account'], 'API Token check failed');

      $this->taxdooClient->setApiKey("n71fz4tz7n0134tz4t3" . rand(1,10000000)); //probably wrong. If it's someone's key we get a bitcoin :-)

      $response = $this->taxdooClient->checkApiKey();
      $this->assertEquals(false, $response, 'API Token check should have failed');

      $this->taxdooClient->setApiKey($this->scopeConfig->getValue( //back being right
          TaxdooConfig::TAXDOO_APIKEY,
          ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
      ));
  }

  public function testSimpleOrderPostGetDelete()
  {
      $order = require __DIR__ . '/../_files/payloads/simple_order.php';
      $orderPayload = [];
      $orderPayload['orders'][] = $order;
      $randomTransactionNumber = $order['channel']['transactionNumber'];

      $this->transactionsToBeCleaned[] = $randomTransactionNumber;
      $postResponse = $this->taxdooClient->postResource('orders', $orderPayload);
      $this->assertEquals('success',$postResponse['status'], 'POST method failed');
      $this->assertEquals(1,$postResponse['insertedRows'], 'Wrong number of inserted rows');

      $getResponse = $this->taxdooClient->getResource('transactions',$randomTransactionNumber);
      $this->assertEquals($randomTransactionNumber, $getResponse['transactions'][0]['channel']['transactionNumber'], 'Invalid transaction number');
      $this->assertEquals("Hoheluftchaussee 13", $getResponse['transactions'][0]['senderAddress']['street'], 'Invalid transaction number');
      $this->assertEquals('24-WG082-blue', $getResponse['transactions'][0]['productIdentifier'], 'Invalid transaction number');

      $transactionId = $getResponse['transactions'][0]['id'];
      $deleteResponse = $this->taxdooClient->deleteResource('transactions',$transactionId);
      $this->assertEquals('success', $deleteResponse['status'], 'Deletion of test transaction failed');
  }

  public function testComplexOrderPostGetDelete()
  {
      $order = require __DIR__ . '/../_files/payloads/order_two_objects.php';
      $orderPayload = [];
      $orderPayload['orders'][] = $order;
      $randomTransactionNumber = $order['channel']['transactionNumber'];

      $this->transactionsToBeCleaned[] = $randomTransactionNumber;
      $postResponse = $this->taxdooClient->postResource('orders', $orderPayload);
      $this->assertEquals('success',$postResponse['status'], 'POST method failed');
      $this->assertEquals(2,$postResponse['insertedRows'], 'Wrong number of inserted rows');

      $getResponse = $this->taxdooClient->getResource('transactions',$randomTransactionNumber);
      $this->assertEquals(2, count($getResponse['transactions']), 'Invalid transactions count');
      $this->assertEquals($randomTransactionNumber, $getResponse['transactions'][0]['channel']['transactionNumber'], 'Invalid transaction number');
      $this->assertEquals("Hoheluftchaussee 13", $getResponse['transactions'][0]['senderAddress']['street'], 'Invalid sender address');
      $this->assertThat($getResponse['transactions'][0]['productIdentifier'], $this->logicalOr( //Transactions (lines of orders) are taken in no particular order by Taxdoo
        $this->equalTo('24-WG082-blue'),
        $this->equalTo('24-WG084')
      ));

      $transactionIds = [$getResponse['transactions'][0]['id'],$getResponse['transactions'][1]['id']];
      $deleteResponse = $this->taxdooClient->deleteResource('transactions',$transactionIds);
      $this->assertEquals('success', $deleteResponse['status'], 'Deletion of test transaction failed');
  }

  public function testRefundPostGetDelete()
  {
      $order = require __DIR__ . '/../_files/payloads/order_two_objects.php';
      $orderPayload = [];
      $orderPayload['orders'][] = $order;
      $randomTransactionNumber = $order['channel']['transactionNumber'];

      $refund = require __DIR__ . '/../_files/payloads/simple_refund.php';
      $refundPayload = [];
      $refundPayload['refunds'][] = $refund;
      $randomRefundNumber = $refund['channel']['refundNumber'];

      // Taxdoo will refuse any refund that doesn't correspond to an order
      // Therefore we need to POST an order, though this has already be tested
      // Perhaps this test should be tested with the previous one?
      $this->transactionsToBeCleaned[] = $randomTransactionNumber;
      $postResponse = $this->taxdooClient->postResource('orders', $orderPayload);
      sleep(2);

      //Posting now the Refund
      $postRefundResponse = $this->taxdooClient->postResource('refunds', $refundPayload);
      $this->assertEquals('success',$postRefundResponse['status'], 'POST method for refund failed');
      $this->assertEquals(2,$postRefundResponse['insertedRows'], 'Wrong number of inserted rows');

      // GETting the same transaction and refund from Taxdoo, verifying its elements
      $getResponse = $this->taxdooClient->getResource('transactions',$randomTransactionNumber,$randomRefundNumber);
      $this->assertEquals(2, count($getResponse['transactions']), 'Invalid transactions count');
      $this->assertEquals("Refund", $getResponse['transactions'][0]['type'], 'Got the wrong type of transaction');
      $this->assertEquals($randomRefundNumber, $getResponse['transactions'][0]['channel']['refundNumber'], 'Invalid transaction (refund) number');
      $this->assertThat($getResponse['transactions'][0]['description'], $this->logicalOr( //Transactions (lines of orders) are taken in no particular order by Taxdoo
        $this->equalTo('Sprite Stasis Ball 65 cm'),
        $this->equalTo('Sprite Foam Yoga Brick')
      ));

      //Cleaning up
      $transactionIds = [];
      foreach ($getResponse['transactions'] as $transaction) {
        $transactionIds[] = $transaction['id'];
      }
      $deleteResponse = $this->taxdooClient->deleteResource('transactions',$transactionIds);
      $this->assertEquals('success', $deleteResponse['status'], 'Deletion of test transaction failed');
  }

  public function testBadRequest()
  {
    $this->expectException(LocalizedException::class);
    $this->expectExceptionMessage('Bad request');
    $order = require __DIR__ . '/../_files/payloads/bad_request.php';
    $orderPayload = [];
    $orderPayload['orders'][] = $order;
    $this->taxdooClient->showResponseErrors(true);

    $postResponse = $this->taxdooClient->postResource('orders', $orderPayload);
  }

  /**
   * Set the configuration.
   *
   * @param array $configData
   * @return $this
   */
  private function _setConfig($configData)
  {
      /** @var \Magento\Config\Model\ResourceModel\Config $config */
      $config = Bootstrap::getObjectManager()->get(\Magento\Config\Model\ResourceModel\Config::class);
      foreach ((array) $configData as $path => $value) {
          $config->saveConfig(
              $path,
              $value,
              ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
              0
          );
    }
      /** @var \Magento\Framework\App\Config\ReinitableConfigInterface $config */
      $config = Bootstrap::getObjectManager()->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class);
      $config->reinit();
      return $this;
      }

  protected function tearDown(): void
  {
    //Each test should clean up after itself. It's actually a part of the test.
    //In case it doesn't work, we (try to) clean everything here
    $getResponse = $this->taxdooClient->getResource('transactions',$this->transactionsToBeCleaned);
    $transactionIds = [];
    foreach ($getResponse['transactions'] as $transaction) {
      $transactionIds[] = $transaction['id'];
    }
    if (!empty($transactionIds)) {
      $this->taxdooClient->deleteResource('transactions',$transactionIds);
    }
  }
}
