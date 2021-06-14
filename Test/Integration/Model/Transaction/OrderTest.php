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
 * @copyright  Copyright (c) 2020 TaxJar. TaxJar is a trademark of TPS Unlimited, Inc. (http://www.taxjar.com)
 * @copyright  Copyright (c) 2021 Andrea Lazzaretti.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

// @codingStandardsIgnoreStart

namespace Taxdoo\VAT\Test\Integration\Model\Transaction;

use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @see https://app.hiptest.com/projects/69435/test-plan/folders/419534/scenarios/2587535
 */
class OrderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\Order
     */
    protected $transactionOrder;

    protected function setUp(): void
    {
        $this->order = Bootstrap::getObjectManager()->get(Order::class);
        $this->transactionOrder = Bootstrap::getObjectManager()->get('Taxdoo\VAT\Model\Transaction\Order');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple.php
     */
    public function testDefaultOrder()
    {
        $order = $this->order->loadByIncrementId('100000002');

        $result = $this->transactionOrder->build($order);

        $this->assertEquals('100000002', $result['orders'][0]['channel']['transactionNumber'], 'Invalid transaction number');
        $this->assertEquals('24-WG082-blue', $result['orders'][0]['items'][0]['productIdentifier'], 'Invalid product identifier');
        $this->assertEquals(0, $result['orders'][0]['shipping'], 'Invalid shipping amount');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple.php
     */
    public function testPositiveDecimals()
    {
        $order = $this->order->loadByIncrementId('100000002');
        $result = $this->transactionOrder->build($order);

        $this->assertEquals(5*27.0, $result['orders'][0]['items'][0]['itemPrice'], 'Invalid price');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple.php
     */
    public function testOrderNoShipping()
    {
        $order = $this->order->loadByIncrementId('100000002');
        $result = $this->transactionOrder->build($order);

        $this->assertEquals(0, $result['orders'][0]['shipping'], 'Invalid shipping amount');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_simple_shipping.php
     */
    public function testOrderShipping()
    {
        $order = $this->order->loadByIncrementId('100000005');
        $result = $this->transactionOrder->build($order);

        $this->assertEquals(5.0, $result['orders'][0]['shipping'], 'Invalid shipping amount');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/order_bundle.php
     */
    public function testBundledProductsOrder()
    {
        $order = $this->order->loadByIncrementId('100000001');
        $result = $this->transactionOrder->build($order);
        $lineItems = $result['orders'][0]['items'];

        $this->assertNotEmpty($lineItems, 'No line items exist.');
        $this->assertEquals(4, count($lineItems), 'Number of line items is incorrect');
        $this->assertEquals(1, $lineItems[0]['quantity'], 'Invalid quantity');
        $this->assertEquals('24-WG082-blue', $lineItems[0]['productIdentifier'], 'Invalid sku.');
        $this->assertEquals(1, $lineItems[1]['quantity'], 'Invalid quantity');
        $this->assertEquals('24-WG084', $lineItems[1]['productIdentifier'], 'Invalid sku.');
        $this->assertEquals(1, $lineItems[2]['quantity'], 'Invalid quantity');
        $this->assertEquals('24-WG086', $lineItems[2]['productIdentifier'], 'Invalid sku.');
        $this->assertEquals(1, $lineItems[3]['quantity'], 'Invalid quantity');
        $this->assertEquals('24-WG088', $lineItems[3]['productIdentifier'], 'Invalid sku.');
    }

    protected function tearDown(): void
    {
    }
}
