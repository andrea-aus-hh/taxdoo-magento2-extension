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

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\Exception\LocalizedException;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;
use Taxdoo\VAT\Model\Logger;
use Taxdoo\VAT\Model\Transaction\OrderFactory;
use Taxdoo\VAT\Model\Transaction\RefundFactory;

use \DateTime;
use \DateInterval;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * This class has too many dependencies.
 * The most obvious way to reduce them would be to do the filtering somewhere else.
 */

class Backfill
{

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Store\Model\StoreManager
     */
    protected $storeManager;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\RefundFactory
     */
    protected $refundFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var \Magento\Framework\Api\Search\FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Taxdoo\SalesTax\Model\Logger
     */
    protected $logger;

    /**
     * @var TaxdooConfig
     */
    protected $taxdooConfig;

    /**
     * @param RequestInterface $request
     * @param StoreManager $storeManager
     * @param OrderFactory $orderFactory
     * @param RefundFactory $refundFactory
     * @param Logger $logger
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TaxdooConfig $taxdooConfig
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        RequestInterface $request,
        StoreManager $storeManager,
        OrderFactory $orderFactory,
        RefundFactory $refundFactory,
        Logger $logger,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxdooConfig $taxdooConfig
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->refundFactory = $refundFactory;
        $this->logger = $logger->setFilename(TaxdooConfig::TAXDOO_TRANSACTIONS_LOG)->force();
        $this->orderRepository = $orderRepository;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxdooConfig = $taxdooConfig;
    }

    /**
     * Start the transaction backfill process
     *
     * @param array $data
     * @return $this
     */
    public function start(
        array $data = []
    ) {
        // @codingStandardsIgnoreEnd

        $this->apiKey = $this->taxdooConfig->getApiKey();

        if (!$this->apiKey) {
            // @codingStandardsIgnoreStart
            throw new LocalizedException(__('Could not sync transactions with Taxdoo. Please make sure you have an API key.'));
            // @codingStandardsIgnoreEnd
        }

        $statesToMatch = ['complete', 'closed'];
        $fromDateParam = $this->request->getParam('from_date');
        $toDateParam = $this->request->getParam('to_date');

        if (isset($data['from_date'])) {
            $fromDateParam = $data['from_date'];
        }

        if (isset($data['to_date'])) {
            $toDateParam = $data['to_date'];
        }

        $this->logger->log('Initializing Taxdoo transaction sync');

        $fromDate = $this->_fromDate($fromDateParam);
        $toDate = $this->_toDate($toDateParam);

        if ($fromDate > $toDate) {
            throw new LocalizedException(__("To date can't be earlier than from date."));
        }

        $this->logger->log('Finding ' . implode(', ', $statesToMatch)
                           . ' transactions from '
                           . $fromDate->format('m/d/Y')
                           . ' - '
                           . $toDate->format('m/d/Y'));

        $storeId = $this->_storeId();

        $orders = $this->_createFilters($storeId, $statesToMatch, $fromDate, $toDate);

        $this->logger->log(count($orders) . ' transaction(s) found');

        // This process can take awhile
        // @codingStandardsIgnoreStart
        // Magento deprecates this function - how shall we replace it?
        set_time_limit(0);
        // @codingStandardsIgnoreEnd
        ignore_user_abort(true);

        foreach ($orders as $order) {
            $orderTransaction = $this->orderFactory->create();
            $orderTransaction->forceSync(); //We override the "Transaction Sync" setting.

            if (!$orderTransaction->isSyncable($order)) {
                $this->logger->log('Order #' . $order->getIncrementId() . ' is not syncable', 'skip');
                continue;
            }

            $orderTransaction->build($order);
            $orderTransaction->push();

            $creditMemos = $order->getCreditmemosCollection();

            foreach ($creditMemos as $creditMemo) {
                $refundTransaction = $this->refundFactory->create();
                $refundTransaction->build($order, $creditMemo);
                $refundTransaction->push();
            }
        }

        return $this;
    }

    /**
     * Filter orders to sync by order state (e.g. completed, closed)
     *
     * @param string $state
     * @return \Magento\Framework\Api\Filter
     */
    protected function orderStateFilter($state)
    {
        return $this->filterBuilder->setField('state')->setValue($state)->create();
    }

    private function _fromDate($fromDateParam)
    {
        $fromDate = (new DateTime())->sub(new DateInterval('P1D'));
        if (!empty($fromDateParam)) {
            $fromDate = (new DateTime($fromDateParam));
        }
        return $fromDate;
    }

    private function _toDate($toDateParam)
    {
        $toDate = (new DateTime());
        if (!empty($toDateParam)) {
            $toDate = (new DateTime($toDateParam));
        }
        return $toDate;
    }

    private function _storeId()
    {
        $storeId = $this->request->getParam('store');
        $websiteId = $this->request->getParam('website');

        // If the store id is empty but the website id is defined, load stores that match the website id
        if ($storeId === null && !($websiteId === null)) {
            $storeId = [];
            foreach ($this->storeManager->getStores() as $store) {
                if ($store->getWebsiteId() == $websiteId) {
                    $storeId[] = $store->getId();
                }
            }
        }

        return $storeId;
    }

    private function _createFilters($storeId, $statesToMatch, $fromDate, $toDate)
    {
      // If the store id is defined, build a filter based on it
        if (!($storeId === null) && !empty($storeId)) {
            $storeFilter = $this->filterBuilder->setField('store_id')
              ->setConditionType(is_array($storeId) ? 'in' : 'eq')
              ->setValue($storeId)
              ->create();

            $storeFilterGroup = $this->filterGroupBuilder
              ->setFilters([$storeFilter])
              ->create();

            $this->logger->log('Limiting transaction sync to store id(s): ' .
              (is_array($storeId) ? implode(',', $storeId) : $storeId));
        }

        $fromDate->setTime(0, 0, 0);
        $toDate->setTime(23, 59, 59);

        $fromFilter = $this->filterBuilder->setField('created_at')
          ->setConditionType('gteq')
          ->setValue($fromDate->format('Y-m-d H:i:s'))
          ->create();

        $fromFilterGroup = $this->filterGroupBuilder
          ->setFilters([$fromFilter])
          ->create();

        $toFilter = $this->filterBuilder->setField('created_at')
          ->setConditionType('lteq')
          ->setValue($toDate->format('Y-m-d H:i:s'))
          ->create();

        $toFilterGroup = $this->filterGroupBuilder
          ->setFilters([$toFilter])
          ->create();

        $stateFilterGroup = $this->filterGroupBuilder
          ->setFilters(array_map([$this, 'orderStateFilter'], $statesToMatch))
          ->create();

        $filterGroups = [$fromFilterGroup, $toFilterGroup, $stateFilterGroup];

        if (isset($storeFilterGroup)) {
            $filterGroups[] = $storeFilterGroup;
        }

        $criteria = $this->searchCriteriaBuilder
          ->setFilterGroups($filterGroups)
          ->create();

        $orderResult = $this->orderRepository->getList($criteria);
        $orders = $orderResult->getItems();

        return $orders;
    }
}
