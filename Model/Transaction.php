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

namespace Taxdoo\VAT\Model;

use Magento\Bundle\Model\Product\Price;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Taxdoo\VAT\Helper\Data as TaxdooHelper;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

class Transaction
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Taxdoo\VAT\Model\Logger
     */
    protected $logger;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * @var \Taxdoo\VAT\Model\ClientFactory
     */
    protected $clientFactory;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * @var \Taxdoo\VAT\Model\Client
     */
    protected $client;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Taxdoo\VAT\Helper\Data
     */
    protected $helper;

    /**
     * @var TaxdooConfig
     */
    protected $taxdooConfig;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Taxdoo\VAT\Model\ClientFactory $clientFactory
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Taxdoo\VAT\Model\Logger $logger
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param TaxdooHelper $helper
     * @param TaxdooConfig $TaxdooConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Taxdoo\VAT\Model\ClientFactory $clientFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Taxdoo\VAT\Model\Logger $logger,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Sales\Api\TransactionRepositoryInterface $repository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxdooHelper $helper,
        TaxdooConfig $taxdooConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->clientFactory = $clientFactory;
        $this->productRepository = $productRepository;
        $this->regionFactory = $regionFactory;
        $this->logger = $logger->setFilename(TaxdooConfig::TAXDOO_TRANSACTIONS_LOG);
        $this->objectManager = $objectManager;
        $this->eventManager = $eventManager;
        $this->repository = $repository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->helper = $helper;
        $this->taxdooConfig = $taxdooConfig;

        $this->client = $this->clientFactory->create();
        $this->client->showResponseErrors(true);
    }

    /**
     * Check if a transaction is synced
     *
     * @param string $syncDate
     * @return array
     */
    protected function isSynced($syncDate)
    {
        if (empty($syncDate) || $syncDate == '0000-00-00 00:00:00') {
            return false;
        }

        return true;
    }

    /**
     * Build `from` address for Taxdoo request
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function buildFromAddress(
        \Magento\Sales\Model\Order $order
    ) {
        $fromCountry = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_COUNTRY_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
        $fromPostcode = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_POSTCODE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
        $region = $this->regionFactory->create();
        $regionId = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_REGION_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
        $region->load($regionId);
        $fromState = $region->getName();
        $fromCity = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_CITY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
        $fromStreet = $this->scopeConfig->getValue('shipping/origin/street_line1',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        ) . ' ' . $this->scopeConfig->getValue('shipping/origin/street_line2',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        return [
            'country' => $fromCountry,
            'zip' => $fromPostcode,
            'state' => $fromState,
            'city' => $fromCity,
            'street' => $fromStreet
        ];
    }

    /**
     * Build `to` address for Taxdoo request
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function buildToAddress(
        \Magento\Sales\Model\Order $order,
        $type = "shipping"
    ) {
        if ($order->getIsVirtual() or $type == "billing") {
            $address = $order->getBillingAddress();
        } else {
            $address = $order->getShippingAddress();
        }

        if ($address->getMiddlename() == "") {
          $fullName = $address->getFirstname() . ' ' . $address->getLastname();
        } else {
          $fullName = $address->getFirstname() . ' ' . $address->getMiddlename() . ' ' . $address->getLastname();
        }

        $toAddress = [
          'fullName' => $fullName,
          'street' => $address->getStreetLine(1) . ' ' . $address->getStreetLine(2),
          'zip' => $address->getPostcode(),
          'city' => $address->getCity(),
          'state' => $address->getRegion(),
          'country' => $address->getCountryId()
        ];

        return $toAddress;
    }

    /**
     * Build line items for Taxdoo request
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $items
     * @param string $type
     * @return array
     */
    protected function buildLineItems($order, $items, $type = 'order') {
        $lineItems = [];
        $parentDiscounts = $this->getParentAmounts('discount', $items, $type);
        $parentTaxes = $this->getParentAmounts('tax', $items, $type);

        foreach ($items as $item) {
            $itemType = $item->getProductType();

            if (is_null($itemType) && method_exists($item, 'getOrderItem')) {
                $creditMemoItem = $item;
                $item = $item->getOrderItem();
                $itemType = $item->getProductType();
            }

            $parentItem = $item->getParentItem();
            $unitPrice = (float) $item->getPrice();
            $quantity = (int) $item->getQtyOrdered();

            if ($type == 'refund' && isset($creditMemoItem)) {
                $quantity = (int) $creditMemoItem->getQty();

                if ($quantity === 0) {
                    continue;
                }
            }

            if (($itemType == 'simple' || $itemType == 'virtual') && $item->getParentItemId()) {
                if (!empty($parentItem) && $parentItem->getProductType() == 'bundle') {
                    if ($parentItem->getProduct()->getPriceType() == Price::PRICE_TYPE_FIXED) {
                        continue;  // Skip children of fixed price bundles
                    }
                } else {
                    continue;  // Skip children of configurable products
                }
            }

            if ($itemType == 'bundle' && $item->getProduct()->getPriceType() != Price::PRICE_TYPE_FIXED) {
                continue;  // Skip dynamic bundle parent item
            }

            if (method_exists($item, 'getOrderItem') && $item->getOrderItem()->getParentItemId()) {
                continue;
            }

            $itemId = $item->getOrderItemId() ? $item->getOrderItemId() : $item->getItemId();
            $discount = (float) $item->getDiscountAmount();
            $tax = (float) $item->getTaxAmount();

            if (isset($parentDiscounts[$itemId])) {
                $discount = $parentDiscounts[$itemId] ?: $discount;
            }

            if ($discount > ($unitPrice * $quantity)) {
                $discount = ($unitPrice * $quantity);
            }

            if (isset($parentTaxes[$itemId])) {
                $tax = $parentTaxes[$itemId] ?: $tax;
            }

            if ($type == "order"){
              $lineItem = [
              'quantity' => $quantity,
              'productIdentifier' => $item->getSku(),
              'description' => $item->getName(),
              'itemPrice' => $unitPrice * $quantity,
              'channelItemNumber' => $itemId,
              'discount' => $discount
            ];
          } else if ($type == "refund") {
            $lineItem = [
              'quantity' => $quantity,
              'description' => $item->getName(),
              'itemPrice' => -$unitPrice * $quantity,
              'channelItemNumber' => $itemId,
              'discount' => $discount,
            ];
          }

            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }


    /**
     * Get parent amounts (discounts, tax, etc) for configurable / bundle products
     *
     * @param string $attr
     * @param array $items
     * @param string $type
     * @return array
     */
    protected function getParentAmounts($attr, $items, $type = 'order') {
        $parentAmounts = [];

        foreach ($items as $item) {
            $parentItemId = null;

            if ($item->getParentItemId()) {
                $parentItemId = $item->getParentItemId();
            }

            if (method_exists($item, 'getOrderItem') && $item->getOrderItem()->getParentItemId()) {
                $parentItemId = $item->getOrderItem()->getParentItemId();
            }

            if (isset($parentItemId)) {
                switch ($attr) {
                    case 'discount':
                        $amount = (float) (($type == 'order') ? $item->getDiscountAmount() : $item->getDiscountRefunded());
                        break;
                    case 'tax':
                        $amount = (float) (($type == 'order') ? $item->getTaxAmount() : $item->getTaxRefunded());
                        break;
                }

                if (isset($parentAmounts[$parentItemId])) {
                    $parentAmounts[$parentItemId] += $amount;
                } else {
                    $parentAmounts[$parentItemId] = $amount;
                }
            }
        }

        return $parentAmounts;
    }


    /**
     * @param int $id
     *
     * @return \Magento\Sales\Api\Data\TransactionInterface[]
     */
    public function getTransactionByOrderId($id)
    {
        $this->searchCriteriaBuilder->addFilter('order_id', $id);
        $list = $this->repository->getList(
            $this->searchCriteriaBuilder->create()
        );

        return $list->getItems();
    }
}
