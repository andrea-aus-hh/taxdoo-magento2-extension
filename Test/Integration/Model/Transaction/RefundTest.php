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

//use Magento\InventoryReservationsApi\Model\CleanupReservationsInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @see https://app.hiptest.com/projects/69435/test-plan/folders/419534/scenarios/2587535
 */
class RefundTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\Order
     */
    protected $transactionOrder;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\Refund
     */
    protected $transactionRefund;

    protected function setUp(): void
    {
        $this->order = Bootstrap::getObjectManager()->get(Order::class);
        $this->transactionOrder = Bootstrap::getObjectManager()->get('Taxdoo\VAT\Model\Transaction\Order');
        $this->transactionRefund = Bootstrap::getObjectManager()->get('Taxdoo\VAT\Model\Transaction\Refund');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_bundle_partial.php
     */
    public function testDefaultRefund()
    {
        $order = $this->order->loadByIncrementId('100000001');
        $creditmemo = $order->getCreditmemosCollection()->getFirstItem();
        $result = $this->transactionRefund->build($order, $creditmemo);
        $this->order->reset();

        if (isset($result['refunds'][0]['items'])) {
            $lineItems = $result['refunds'][0]['items'];

            $this->assertEquals(2, count($lineItems), 'Number of line items is incorrect');
            $this->assertEquals(1, $lineItems[0]['quantity'], 'Invalid quantity');
            $this->assertEquals('Sprite Stasis Ball 65 cm', $lineItems[0]['description'], 'Invalid description.');
            $this->assertEquals(1, $lineItems[1]['quantity'], 'Invalid quantity');
            $this->assertEquals('Sprite Foam Yoga Brick', $lineItems[1]['description'], 'Invalid description.');
            $this->assertArrayNotHasKey(2, $lineItems);
            $this->assertArrayNotHasKey(3, $lineItems);
        }

    }


    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple_partial.php
     */
    public function testPartialRefund()
    {
        $order = $this->order->loadByIncrementId('100000004');
        $creditmemo = $order->getCreditmemosCollection()->getFirstItem();
        $result = $this->transactionRefund->build($order, $creditmemo);
        $this->order->reset();

        $this->assertEquals(1, $result['refunds'][0]['items'][0]['quantity'], 'Incorrect quantity');
        $this->assertEquals(-27.0, $result['refunds'][0]['items'][0]['itemPrice'], 'Incorrect item price');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple_partial.php
     */
    public function testPartialRefundLineItems()
    {
        $order = $this->order->loadByIncrementId('100000004');
        $creditmemo = $order->getCreditmemosCollection()->getFirstItem();
        $result = $this->transactionRefund->build($order, $creditmemo);
        $this->order->reset();

        $this->assertEquals(1, count($result['refunds'][0]['items']), 'Invalid number of items');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple.php
     */
    public function testShippingNotRefunded()
    {
        $order = $this->order->loadByIncrementId('100000002');
        $creditmemo = $order->getCreditmemosCollection()->getFirstItem();
        $result = $this->transactionRefund->build($order, $creditmemo);
        $this->order->reset();
        $this->assertEquals(0, $result['refunds'][0]['shipping'], 'Incorrect refund amount');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_simple_shipping.php
     */
    public function testShippingRefunded()
    {
        $order = $this->order->loadByIncrementId('100000005');
        $creditmemo = $order->getCreditmemosCollection()->getFirstItem();

        $result = $this->transactionRefund->build($order, $creditmemo);
        $this->order->reset();

        $this->assertEquals(-5., $result['refunds'][0]['shipping'], 'Invalid shipping refunded');
    }

    /**
     * @magentoDataFixture ../../../../app/code/Taxdoo/VAT/Test/Integration/_files/transaction/refund_bundle_partial.php
     */
    public function testBundledProductsPartialRefund()
    {
       $order = $this->order->loadByIncrementId('100000001');
       $creditmemo = $order->getCreditmemosCollection()->getFirstItem();
       $result = $this->transactionRefund->build($order, $creditmemo);
       $lineItems = $result['refunds'][0]['items'];

       $this->assertEquals(2, count($lineItems), 'Number of line items is incorrect');
       $this->assertEquals(1, $lineItems[0]['quantity'], 'Invalid quantity');
       $this->assertEquals('Sprite Stasis Ball 65 cm', $lineItems[0]['description'], 'Invalid sku.');
       $this->assertEquals(1, $lineItems[1]['quantity'], 'Invalid quantity');
       $this->assertEquals('Sprite Foam Yoga Brick', $lineItems[1]['description'], 'Invalid sku.');
       $this->assertArrayNotHasKey(2, $lineItems);
       $this->assertArrayNotHasKey(3, $lineItems);
    }

    protected function tearDown(): void
    {
    }
}
