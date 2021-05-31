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

class Refund extends \Taxdoo\VAT\Model\Transaction
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $originalOrder;

    /**
     * @var \Magento\Sales\Model\Order\Creditmemo
     */
    protected $originalRefund;

    /**
     * @var array
     */
    protected $request;

    /**
     * Build a refund transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return array
     */
    public function build(
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Creditmemo $creditmemo
    ) {
        $subtotal = (float) $creditmemo->getSubtotal();
        $shipping = (float) $creditmemo->getShippingAmount();
        $discount = (float) $creditmemo->getDiscountAmount();
        $salesTax = (float) $creditmemo->getTaxAmount();
        $adjustment = (float) $creditmemo->getAdjustment();
        $itemDiscounts = 0;
        $currencyCode = $creditmemo->getOrderCurrencyCode();

        $this->originalOrder = $order;
        $this->originalRefund = $creditmemo;

        $createdAt = new \DateTime($order->getCreatedAt());

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

        $refund = [
          'channel' => array(
            "identifier" => TaxdooConfig::TAXDOO_MAGENTO_IDENTIFIER,
            "transactionNumber" => $order->getIncrementId(),
            "refundNumber" => $creditmemo->getIncrementId()
          ),
          'source' => array(
            "identifier" => TaxdooConfig::TAXDOO_MAGENTO_IDENTIFIER,
            "transactionNumber" => $order->getIncrementId(),
            "refundNumber" => $creditmemo->getIncrementId()
          ),
          'paymentDate' => $transactionDate->format(\DateTime::RFC3339),
          'transactionCurrency' => $currencyCode,
          'items' => $this->buildLineItems($order, $creditmemo->getAllItems(), 'refund'),
          'shipping' => -$shipping
        ];



        if (isset($this->refund['items'])) {
            $adjustmentFee = $creditmemo->getAdjustmentNegative();
            $adjustmentRefund = $creditmemo->getAdjustmentPositive();

            // Discounts on credit memos act as fees and shouldn't be included in $itemDiscounts
            foreach ($this->refund['items'] as $k => $lineItem) {
                if ($subtotal != 0) {
                    $lineItemSubtotal = $lineItem['itemPrice'] * $lineItem['quantity'];
                    $this->refund['items'][$k]['discount'] += ($adjustmentFee * ($lineItemSubtotal / $subtotal));
                }

                $itemDiscounts += $lineItem['discount'];
            }

            if ($adjustmentRefund) {
                $this->request['adjustmentAmount'] = $adjustmentRefund;
            }
        }

        if ((abs($discount) - $itemDiscounts) > 0) {
            $shippingDiscount = abs($discount) - $itemDiscounts;
            $this->request['shipping'] = $shipping - $shippingDiscount;
        }

        $refundsArray = [];
        $refundsArray['refunds'][] = $refund;

        $this->request = $refundsArray; //TEMPORANEO, GUARDA LA ROBA COMMENTATA PRIMA

        return $this->request;
    }

    /**
     * Push refund transaction to Taxdoo
     *
     * @param string|null $forceMethod
     * @return void
     */
    public function push($forceMethod = null) {
        $refundUpdatedAt = $this->originalRefund->getUpdatedAt();
        $refundSyncedAt = $this->originalRefund->getTdSalestaxSyncDate();
        $this->apiKey = $this->taxdooConfig->getApiKey($this->originalOrder->getStoreId());

        if (!$this->isSynced($refundSyncedAt)) {
            $method = 'POST'; // This is the ghost of the feature that allowed to call a PUT method to modify a transaction.
                              // That feature is not implemented yet
        } else {
            $this->logger->log('Refund #' . $this->request['refunds'][0]['channel']['transactionNumber']
                                        . ' for order #' . $this->request['refunds'][0]['source']['transactionNumber']
                                        . ' has already been synced', 'skip');
            return;
        }

        if ($this->apiKey) {
            $this->client->setApiKey($this->apiKey);
        }

        if ($forceMethod) {
            $method = $forceMethod;
        }

        try {
            $this->logger->log('Pushing refund / credit memo #' . $this->request['refunds'][0]['channel']['transactionNumber']
                                    . ' for order #' . $this->request['refunds'][0]['source']['transactionNumber']
                                    . ': ' . json_encode($this->request), $method);

            if ($method == 'POST') {
                $response = $this->client->postResource('refunds', $this->request);
                $this->logger->log('Refund #' . $this->request['refunds'][0]['channel']['transactionNumber'] . ' created: ' . json_encode($response), 'api');
                $this->originalRefund->setTdSalestaxSyncDate(gmdate('Y-m-d H:i:s'));
                $this->originalRefund->getResource()->saveAttribute($this->originalRefund, 'td_salestax_sync_date');
            }

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->log('Error: ' . $e->getMessage(), 'error');
            $error = json_decode($e->getMessage());

            // Retry push for not found records using POST
            if (!$forceMethod && $method == 'PUT' && $error && $error->status == 404) {
                $this->logger->log('Attempting to create refund / credit memo #' . $this->request['refunds'][0]['channel']['transactionNumber'], 'retry');
                return $this->push('POST');
            }
        }
    }
}
