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

namespace Taxdoo\VAT\Setup;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Taxdoo\VAT\Model\ClientFactory;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    private $attributeRepository;

    /**
     * @var \Taxdoo\VAT\Model\Client
     */
    private $client;

    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    private $resourceConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param \Magento\Eav\Model\AttributeRepository $attributeRepository
     * @param ClientFactory $clientFactory
     * @param Config $eavConfig
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        ClientFactory $clientFactory,
        Config $eavConfig,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->client = $clientFactory->create();
        $this->eavConfig = $eavConfig;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eventManager = $eventManager;
        $this->resourceConfig = $resourceConfig;
        $this->scopeConfig = $scopeConfig;
    }

    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        
    }
}
