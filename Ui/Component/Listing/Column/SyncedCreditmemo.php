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

namespace Taxdoo\VAT\Ui\Component\Listing\Column;

use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

use \Magento\Sales\Api\CreditmemoRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use \Magento\Framework\Api\SearchCriteriaBuilder;
use \Magento\Framework\Stdlib\DateTime\Timezone;
use \Magento\Framework\Exception\NoSuchEntityException;

use \Datetime;

class SyncedCreditmemo extends Column
{
    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteria;

    /**
     * @var Timezone
     */
    protected $timezone;

    /**
     * @var \Taxdoo\VAT\Model\Logger
     */
    protected $logger;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param SearchCriteriaBuilder $criteria
     * @param Timezone $timezone
     * @param \Taxdoo\VAT\Model\Logger $logger
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CreditmemoRepositoryInterface $creditmemoRepository,
        SearchCriteriaBuilder $criteria,
        Timezone $timezone,
        \Taxdoo\VAT\Model\Logger $logger,
        array $components = [],
        array $data = []
    ) {
        $this->creditmemoRepository = $creditmemoRepository;
        $this->searchCriteria  = $criteria;
        $this->timezone = $timezone;
        $this->logger = $logger->setFilename(TaxdooConfig::TAXDOO_DEFAULT_LOG);
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $creditmemoSyncDate = '';

                try {
                    if (isset($item['td_salestax_sync_date'])) {
                        $creditmemoSyncDate = $this->timezone->formatDate(
                            new DateTime($item['td_salestax_sync_date']),
                            \IntlDateFormatter::MEDIUM,
                            true
                        );
                    }
                } catch (NoSuchEntityException $e) {
                    $this->logger->log($e->getMessage() . ', entity id: ' . $item['entity_id']);
                }

                $item[$this->getName()] = $creditmemoSyncDate;
            }
        }

        return $dataSource;
    }
}
