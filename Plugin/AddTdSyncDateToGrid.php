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

namespace Taxdoo\VAT\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Grid\Collection as CreditmemoGridCollection;

class AddTdSyncDateToGrid
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * Join td_salestax_sync_date in the order and creditmemo admin grids
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param $collection
     * @return \Magento\Framework\Data\Collection
     */
    public function afterGetReport(
        CollectionFactory $subject,
        $collection
    ) {
        if ($collection instanceof OrderGridCollection) {
            $collection->getSelect()->joinLeft(
                ['orders' => $this->resource->getTableName('sales_order')],
                'main_table.entity_id = orders.entity_id',
                'td_salestax_sync_date'
            );
        }

        if ($collection instanceof CreditmemoGridCollection) {
            $collection->getSelect()->joinLeft(
                ['creditmemos' => $this->resource->getTableName('sales_creditmemo')],
                'main_table.entity_id = creditmemos.entity_id',
                'td_salestax_sync_date'
            );
            $collection->addFilterToMap('created_at', 'main_table.created_at');
            $collection->addFilterToMap('base_grand_total', 'main_table.base_grand_total');
            $collection->addFilterToMap('increment_id', 'main_table.increment_id');
            $collection->addFilterToMap('state', 'main_table.state');
            $collection->addFilterToMap('store_id', 'main_table.store_id');
        }

        return $collection;
    }
}
