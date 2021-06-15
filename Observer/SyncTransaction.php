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
use Magento\Sales\Api\OrderRepositoryInterface;
use Taxdoo\VAT\Model\Transaction\OrderFactory;
use Taxdoo\VAT\Model\Transaction\RefundFactory;
use Taxdoo\VAT\Helper\Data as TaxdooHelper;
use Taxdoo\VAT\Model\Logger;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

class SyncTransaction implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

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
     * @var \Taxdoo\VAT\Model\Logger
     */
    protected $logger;

    /**
     * @var TaxdooConfig
     */
    protected $taxdooConfig;

    /**
     * @param ManagerInterface $messageManager
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderFactory $orderFactory
     * @param RefundFactory $refundFactory
     * @param Registry $registry
     */
    public function __construct(
        ManagerInterface $messageManager,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        RefundFactory $refundFactory,
        \Taxdoo\VAT\Model\Logger $logger,
        Registry $registry,
        TaxdooHelper $helper,
        TaxdooConfig $taxdooConfig
    ) {
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->refundFactory = $refundFactory;
        $this->registry = $registry;
        $this->helper = $helper;
        $this->logger = $logger->setFilename(TaxdooConfig::TAXDOO_DEFAULT_LOG);
        $this->taxdooConfig = $taxdooConfig;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(
        Observer $observer
    ) {
        if ($observer->getData('order_id')) {
            $order = $this->orderRepository->get($observer->getData('order_id'));
        } else {
            $order = $observer->getEvent()->getOrder();
        }

        $eventName = $observer->getEvent()->getName();
        $orderTransaction = $this->orderFactory->create();

        if ($orderTransaction->isSyncable($order, false)) { //We're not forcing the sync
            if (!$this->registry->registry('taxdoo_sync_' . $eventName)) {
                $this->registry->register('taxdoo_sync_' . $eventName, true);
            } else {
                return $this;
            }

            try {
                $orderTransaction->build($order);
                $orderTransaction->push();

                $creditmemos = $order->getCreditmemosCollection();

                foreach ($creditmemos as $creditmemo) {
                    $refundTransaction = $this->refundFactory->create();
                    $refundTransaction->build($order, $creditmemo);
                    $refundTransaction->push();
                }

                if ($observer->getData('order_id')) {
                    $this->messageManager->addSuccessMessage(__('Order successfully synced to Taxdoo.'));
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        } else {
            if ($observer->getData('order_id')) {
                $this->messageManager->addErrorMessage(__('This order was not synced to Taxdoo.'));
            }
        }

        return $this;
    }
}
