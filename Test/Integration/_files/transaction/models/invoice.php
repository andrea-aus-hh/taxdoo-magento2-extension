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

use Magento\Sales\Api\InvoiceItemRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Invoice\ItemFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\ObjectManager;

$objectManager = ObjectManager::getInstance();

/** @var InvoiceService $invoiceService */
$invoiceService = $objectManager->get(InvoiceService::class);
$invoice = $invoiceService->prepareInvoice($order);
$invoice->setIncrementId($order->getIncrementId());
$invoice->register();

/** @var InvoiceRepositoryInterface $invoiceRepository */
$invoiceRepository = $objectManager->get(InvoiceRepositoryInterface::class);
$invoice = $invoiceRepository->save($invoice);

/** @var ItemFactory $itemFactory */
$itemFactory = $objectManager->get(ItemFactory::class);
/** @var InvoiceItemRepositoryInterface $itemRepository */
$itemRepository = $objectManager->get(InvoiceItemRepositoryInterface::class);

foreach ($order->getAllItems() as $item) {
    $invoiceItem = $itemFactory->create(['data' => $item->getData()]);
    $invoiceItem->setId(null)
        ->setInvoice($invoice)
        ->setOrderItem($item)
        ->setQty($item->getQtyInvoiced());
    $itemRepository->save($invoiceItem);
}
