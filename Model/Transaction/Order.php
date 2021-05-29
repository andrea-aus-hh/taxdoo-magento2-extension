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

namespace Taxdoo\VAT\Model\Transaction;

use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

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
     * Build an order transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function build(
        \Magento\Sales\Model\Order $order
    ) {
        $createdAt = new \DateTime($order->getCreatedAt());
        $subtotal = (float) $order->getSubtotal();
        $shipping = (float) $order->getShippingAmount();
        $discount = (float) $order->getDiscountAmount();
        $shippingDiscount = (float) $order->getShippingDiscountAmount();
        $salesTax = (float) $order->getTaxAmount();
        $currencyCode = $order->getOrderCurrencyCode();

        $shipments = $order->getShipmentsCollection();
        $currentShipment = $shipments->getFirstItem();
        $currentShipmentCreatedAt = new \DateTime($currentShipment->getCreatedAt());

        $invoices = $order->getInvoiceCollection();
        $currentInvoice = $invoices->getFirstItem();
        $currentInvoiceCreatedAt = new \DateTime($currentInvoice->getCreatedAt());

        $transactions = $this->getTransactionByOrderId($order->getIncrementId());

        if (!empty($transactions)) { //If there is a transaction (payment), we use it. Otherwise we use the Invoice date
          $currentTransaction = $transactions->getFirstItem();
          $transactionDate = new \DateTime($currentTransaction->getCreatedAt());
        } else {
          $transactionDate = new \DateTime($currentInvoice->getCreatedAt());
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethod();
        $paymentId = $payment->getParentId();

        $this->originalOrder = $order;


        $newOrder = [
          'type' => 'Sale',
          'channel' => array(
            'identifier' => TaxdooConfig::TAXDOO_MAGENTO_IDENTIFIER,
            'transactionNumber' => $order->getIncrementId()
          ),
          'paymentDate' => $transactionDate->format(\DateTime::RFC3339),
          'sentDate' => $currentShipmentCreatedAt->format(\DateTime::RFC3339),
          'deliveryAddress' => $this->buildToAddress($order),
          'billingAddress' => $this->buildToAddress($order, "billing"),
          'senderAddress' => $this->buildFromAddress($order),
          'shipping' => $shipping,
          'transactionCurrency' => $currencyCode,
          'items' => $this->buildLineItems($order, $order->getAllItems()),
          'paymentChannel' => $paymentMethod,
          'paymentNumber' => $paymentId,
          'invoiceDate' => $currentInvoiceCreatedAt->format(\DateTime::RFC3339),
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
    public function push($forceMethod = null) {
        $orderUpdatedAt = $this->originalOrder->getUpdatedAt();
        $orderSyncedAt = $this->originalOrder->getTdSalestaxSyncDate();
        $this->apiKey = $this->taxdooConfig->getApiKey($this->originalOrder->getStoreId());

        if (!$this->isSynced($orderSyncedAt)) {
            $method = 'POST';
        } else {
            if ($orderSyncedAt < $orderUpdatedAt) {
                $method = 'PUT';
            } else {
                $this->logger->log('Order #' . $this->request['orders'][0]['channel']['transactionNumber'] . ' not updated since last sync', 'skip');
                return;
            }
        }

        if ($this->apiKey) {
            $this->client->setApiKey($this->apiKey);
        }

        if ($forceMethod) {
            $method = $forceMethod;
        }

        try {
            $this->logger->log('Pushing order #' . $this->request['orders'][0]['channel']['transactionNumber'] . ': ' . json_encode($this->request), $method);

            if ($method == 'POST') {
                $response = $this->client->postResource('orders', $this->request);
                $this->logger->log('Order #' . $this->request['orders'][0]['channel']['transactionNumber'] . ' created in Taxdoo: ' . json_encode($response), 'api');
            } else {
                $response = $this->client->putResource('orders', $this->request['transaction_id'], $this->request);
                $this->logger->log('Order #' . $this->request['orders'][0]['channel']['transactionNumber'] . ' updated in Taxdoo: ' . json_encode($response), 'api');
            }

            $this->originalOrder->setTdSalestaxSyncDate(gmdate('Y-m-d H:i:s'))->save();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->log('Error: ' . $e->getMessage(), 'error');
            $error = json_decode($e->getMessage());

            // Retry push for not found records using POST
            if (!$forceMethod && $method == 'PUT' && $error && $error->status == 404) {
                $this->logger->log('Attempting to create order #' . $this->request['orders'][0]['channel']['transactionNumber'], 'retry');
                return $this->push('POST');
            }

            // Retry push for existing records using PUT
            if (!$forceMethod && $method == 'POST' && $error && $error->status == 422) {
                $this->logger->log('Attempting to update order #' . $this->request['orders'][0]['channel']['transactionNumber'], 'retry');
                return $this->push('PUT');
            }
        }
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
        \Magento\Sales\Model\Order $order,
        $forceSync = false
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

        if ((!$storeSyncEnabled || (!$websiteSyncEnabled && !$storeSyncEnabled)) && !$forceSync) {
            return false;
        }

        return true;
    }
}
