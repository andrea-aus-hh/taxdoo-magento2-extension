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
 * @copyright  Copyright (c) 2017 TaxJar. TaxJar is a trademark of TPS Unlimited, Inc. (http://www.taxjar.com)
 * @copyright  Copyright (c) 2021 Andrea Lazzaretti.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace Taxdoo\VAT\Model\Transaction;

use Taxdoo\VAT\Model\Configuration as TaxdooConfig;
use Taxdoo\VAT\Helper\Data as TaxdooHelper;

use \DateTime;

class Order extends \Taxdoo\VAT\Model\Transaction
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $originalOrder;

    /**
     * @var array
     */
    protected $request;

    /**
     * @var boolean
     */
    protected $syncIsForced = false;

    /**
     * Build an order transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param TaxdooHelper $helper
     * @return array
     */
    public function build(
        \Magento\Sales\Model\Order $order
    ) {
        $shipping = (float) $order->getShippingAmount();
        $currencyCode = $order->getOrderCurrencyCode();

        $shipments = $order->getShipmentsCollection();
        $currentShipment = $shipments->getFirstItem();
        $currentShipmentCreatedAt = new DateTime($currentShipment->getCreatedAt());

        $invoices = $order->getInvoiceCollection();
        $currentInvoice = $invoices->getFirstItem();
        $currentInvoiceCreatedAt = new DateTime($currentInvoice->getCreatedAt());

        $transactions = $this->getTransactionByOrderId($order->getIncrementId());

        $transactionDate = new DateTime($currentInvoice->getCreatedAt());
        if (!empty($transactions)) { // If there is a transaction (payment), we use it.
                                     // Otherwise we default to the Invoice date
            $currentTransaction = $transactions->getFirstItem();
            $transactionDate = new DateTime($currentTransaction->getCreatedAt());
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethod();
        $paymentId = $payment->getParentId();

        $this->originalOrder = $order;

        $newOrder = [
          'type' => 'Sale',
          'channel' => [
            'identifier' => TaxdooConfig::TAXDOO_MAGENTO_IDENTIFIER,
            'transactionNumber' => $order->getIncrementId()
          ],
          'paymentDate' => $transactionDate->format(\DateTime::RFC3339),
          'sentDate' => $currentShipmentCreatedAt->format(\DateTime::RFC3339),
          'deliveryAddress' => $this->buildToAddress($order),
          'billingAddress' => $this->buildToAddress($order, "billing"),
          'senderAddress' => $this->buildFromAddress($order),
          'shipping' => $shipping,
          'transactionCurrency' => $currencyCode,
          'items' => $this->buildLineItems($order->getAllItems()),
          'paymentChannel' => $paymentMethod,
          'paymentNumber' => $paymentId,
          'invoiceDate' => $currentInvoiceCreatedAt->format(DateTime::RFC3339),
          'invoiceNumber' => $currentInvoice->getIncrementId(),
        ];
        $ordersArray = [];
        $ordersArray['orders'][] = $newOrder;

        $this->request = $ordersArray;

        return $this->request;
    }

    /**
     * Push an order transaction to Taxdoo
     *
     * @param string|null $forceMethod
     * @return void
     */
    public function push($forceMethod = null)
    {
        $orderSyncedAt = $this->originalOrder->getTdSalestaxSyncDate();
        $this->apiKey = $this->taxdooConfig->getApiKey($this->originalOrder->getStoreId());

        $orderNumber = $this->request['orders'][0]['channel']['transactionNumber'];

        if ($this->isSynced($orderSyncedAt)) {
            $this->logger->log('Order #' . $orderNumber . ' has already been synced', 'skip');
            return;
        }

        $method = 'POST'; // This is the ghost of the feature
                          // that allowed to call a PUT method to modify a transaction.
                          // That feature is not implemented yet

        if ($this->apiKey) {
            $this->client->setApiKey($this->apiKey);
        }

        if ($forceMethod) {
            $method = $forceMethod;
        }

        try {
            $this->logger->log('Pushing order #' . $orderNumber . ': ' . json_encode($this->request), $method);

            if ($method == 'POST') {
                $response = $this->client->postResource('orders', $this->request);
                $this->logger->log('Order #' . $orderNumber . ' created in Taxdoo: ' . json_encode($response), 'api');
                $this->originalOrder->setTdSalestaxSyncDate(gmdate('Y-m-d H:i:s'))->save();
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->log('Error: ' . $e->getMessage(), 'error');
            $this->eventManager->dispatch(
                'transaction_sync_failed',
                ['request' => $this->request, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Enables or disables the forced syncing
     *
     * @param boolean $isForced
     * @return Logger
     */
    public function forceSync()
    {
        $this->syncIsForced = true;
        return $this;
    }

    /**
     * Enables or disables the forced syncing
     *
     * @param boolean $isForced
     * @return Logger
     */
    public function unForceSync()
    {
        $this->syncIsForced = false;
        return $this;
    }

    /**
     * Determines if an order can be synced.
     * It is a function that in the original Taxjar plugin checks many more aspects, that in our case aren't relevant
     * In our case it just checks that the order has been completed or closed, and that transaction sync is active.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    public function isSyncable(
        \Magento\Sales\Model\Order $order
    ) {
        $states = ['complete', 'closed'];

        if (!($order instanceof \Magento\Framework\Model\AbstractModel)) {
            return false;
        }

        if (!in_array($order->getState(), $states)) {
            return false;
        }

        // Check if transaction sync is disabled at the store level OR at the store AND website levels
        // Check also if the syncing should be nonetheless forced - eg for backfilling purposes
        $storeSyncEnabled = $this->helper->isTransactionSyncEnabled($order->getStoreId(), 'store');
        $websiteSyncEnabled = $this->helper->isTransactionSyncEnabled($order->getStore()->getWebsiteId(), 'website');

        if ((!$storeSyncEnabled || (!$websiteSyncEnabled && !$storeSyncEnabled)) && !$this->syncIsForced) {
            return false;
        }

        return true;
    }
}
