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

namespace Taxdoo\VAT\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Taxdoo\VAT\Model\Transaction\OrderFactory;
use Taxdoo\VAT\Model\Transaction\RefundFactory;
use Taxdoo\VAT\Helper\Data as TaxdooHelper;

class SyncRefund implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\RefundFactory
     */
    protected $refundFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Taxdoo\VAT\Helper\Data
     */
    protected $helper;

    /**
     * @param ManagerInterface $messageManager
     * @param OrderFactory $orderFactory
     * @param RefundFactory $refundFactory
     * @param Registry $registry
     */
    public function __construct(
        ManagerInterface $messageManager,
        OrderFactory $orderFactory,
        RefundFactory $refundFactory,
        TaxdooHelper $helper,
        Registry $registry
    ) {
        $this->messageManager = $messageManager;
        $this->orderFactory = $orderFactory;
        $this->refundFactory = $refundFactory;
        $this->helper = $helper;
        $this->registry = $registry;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(
        Observer $observer
    ) {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        $eventName = $observer->getEvent()->getName();
        $orderTransaction = $this->orderFactory->create();

        $orderTransaction->unForceSync(); //Per se redundant, but let's be explicit
        if ($orderTransaction->isSyncable($order)) { //We're not forcing the sync
            if ($this->registry->registry('taxdoo_sync_' . $eventName)) {
                return $this;
            }

            $this->registry->register('taxdoo_sync_' . $eventName, true);

            try {
                $refundTransaction = $this->refundFactory->create();
                $refundTransaction->build($order, $creditmemo);
                $refundTransaction->push();
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this;
    }
}
