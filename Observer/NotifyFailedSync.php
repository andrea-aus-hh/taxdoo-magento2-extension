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

namespace Taxdoo\VAT\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Notification\NotifierInterface;

class NotifyFailedSync implements ObserverInterface
{
    protected $notifierPool;


    public function __construct(
        \Magento\Framework\Notification\NotifierInterface $notifierPool
    ) {
        $this->notifierPool = $notifierPool;
    }

    /**
     * @param  Observer $observer
     * @return $this
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    // @codingStandardsIgnoreStart
    public function execute(Observer $observer)
    {
        $failedRequest = $observer->getData('request');
        $response = $observer->getData('error');
        if (array_key_exists('orders',$failedRequest)) {
          $transactionNumber = $failedRequest['orders'][0]['channel']['transactionNumber'];
          $title = __('The order ' . $transactionNumber . ' hasn\'t been synced to Taxdoo');
        } else if (array_key_exists('refunds',$failedRequest)) {
          $refundNumber = $failedRequest['refunds'][0]['channel']['refundNumber'];
          $transactionNumber = $failedRequest['refunds'][0]['channel']['transactionNumber'];
          $title = __('The refund ' . $refundNumber . ' for the order ' . $transactionNumber . ' hasn\'t been synced to Taxdoo');
        } else if (array_key_exists('transactions',$failedRequest)) {
          $transactionNumber = $failedRequest['transactions'][0]['channel']['transactionNumber'];
          $title = __('The transaction ' . $transactionNumber . ' hasn\'t been synced to Taxdoo');
        } else {
          $title = __('Something went wrong with your last request to Taxdoo.');
        }

        $this->notifierPool->addMinor(
          $title,
          __('Check your API token and the request which follows here: ' . json_encode($failedRequest) . ' Taxdoo response: ' . $response)
        );
        return $this;
    }
}
