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

namespace Taxdoo\VAT\Test\Integration\Model\Transaction;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;

use \DateTime;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @see https://app.hiptest.com/projects/69435/test-plan/folders/419534/scenarios/2587535
 */
class BackfillTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Order
     */
    protected $backfill;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\Order
     */
    protected $transactionOrder;

    protected function setUp(): void
    {
        $this->backfill = Bootstrap::getObjectManager()->get('Taxdoo\VAT\Model\Transaction\Backfill');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple.php
     */
    public function testOneOrder()
    {
        $fromDate = new DateTime('04/01/2021');
        $toDate = new DateTime('04/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(1, count($orders), 'Invalid number of orders');
        $this->assertEquals('100000002', current($orders)->getIncrementId(), 'Invalid order id');

        $fromDate = new DateTime('04/23/2021');
        $toDate = new DateTime('04/25/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(1, count($orders), 'Invalid number of orders');
        $this->assertEquals('100000002', current($orders)->getIncrementId(), 'Invalid order id');

        $fromDate = new DateTime('01/01/2021');
        $toDate = new DateTime('01/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(0, count($orders), 'Invalid number of orders');

        $fromDate = new DateTime('04/01/2021');
        $toDate = new DateTime('04/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['closed'],$fromDate,$toDate);
        $this->assertEquals(0, count($orders), 'Invalid number of orders');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple.php
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple_shipping.php
     */
    public function testTwoOrders()
    {
        $fromDate = new DateTime('04/01/2021');
        $toDate = new DateTime('04/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(1, count($orders), 'Invalid number of orders');
        $this->assertEquals('100000002', current($orders)->getIncrementId(), 'Invalid order id');

        $fromDate = new DateTime('04/23/2021');
        $toDate = new DateTime('04/25/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(1, count($orders), 'Invalid number of orders');
        $this->assertEquals('100000002', current($orders)->getIncrementId(), 'Invalid order id');

        $fromDate = new DateTime('01/01/2021');
        $toDate = new DateTime('01/15/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(1, count($orders), 'Invalid number of orders');
        $this->assertEquals('100000005', current($orders)->getIncrementId(), 'Invalid order id');

        $fromDate = new DateTime('01/01/2021');
        $toDate = new DateTime('04/25/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(2, count($orders), 'Invalid number of orders');
        $this->assertThat(current($orders)->getIncrementId(), $this->logicalOr(
          $this->equalTo('100000002'),
          $this->equalTo('100000005')
        ));

        $fromDate = new DateTime('11/06/1989');
        $toDate = new DateTime('06/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['closed'],$fromDate,$toDate);
        $this->assertEquals(0, count($orders), 'Invalid number of orders');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple.php
     */
    public function testOneRefund()
    {
        $fromDate = new DateTime('04/01/2021');
        $toDate = new DateTime('04/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $creditmemo = current($orders)->getCreditmemosCollection()->getFirstItem();
        $this->assertEquals(81, $creditmemo->getSubtotal(), 'Invalid creditmemo');

        $fromDate = new DateTime('01/01/2021');
        $toDate = new DateTime('01/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(0, count($orders), 'Invalid number of orders');

        $fromDate = new DateTime('04/01/2021');
        $toDate = new DateTime('04/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $this->assertEquals(1, count($orders), 'Invalid number of orders');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple.php
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple_partial.php
     */
    public function testTwoRefunds()
    {
        $fromDate = new DateTime('04/01/2021');
        $toDate = new DateTime('04/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $creditmemo = current($orders)->getCreditmemosCollection()->getFirstItem();
        $this->assertEquals(81, $creditmemo->getSubtotal(), 'Invalid creditmemo content');

        $fromDate = new DateTime('03/01/2021');
        $toDate = new DateTime('03/22/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $creditmemo = current($orders)->getCreditmemosCollection()->getFirstItem();
        $this->assertEquals(27, $creditmemo->getSubtotal(), 'Invalid creditmemo content');

        $fromDate = new DateTime('01/01/2021');
        $toDate = new DateTime('06/30/2021');
        $orders = $this->backfill->createListOfOrders(null,['complete','closed'],$fromDate,$toDate);
        $creditmemos = [];
        foreach ($orders as $currentOrder) {
          $creditmemos[] = $currentOrder->getCreditmemosCollection()->getFirstItem();
        }
        $this->assertEquals(2, count($creditmemos), 'Invalid number of creditmemos');
    }

    protected function tearDown(): void
    {
    }
}
