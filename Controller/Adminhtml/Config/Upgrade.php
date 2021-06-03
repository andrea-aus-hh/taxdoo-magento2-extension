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

namespace Taxdoo\VAT\Controller\Adminhtml\Config;

use Magento\Backend\App\Action\Context;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

class Upgrade extends \Magento\Backend\App\AbstractAction
{
    const ADMIN_RESOURCE = 'Magento_Tax::manage_tax';

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $resourceConfig;

    /**
     * @var \Magento\Framework\App\Config\ReinitableConfigInterface
     */
    protected $reinitableConfig;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $resourceConfig
     * @param ReinitableConfigInterface $reinitableConfig
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Config $resourceConfig,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->eventManager = $context->getEventManager();
        $this->scopeConfig = $scopeConfig;
        $this->resourceConfig = $resourceConfig;
        $this->reinitableConfig = $reinitableConfig;
        parent::__construct($context);
    }

    /**
     * Connect to Taxdoo
     *
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $this->resourceConfig->saveConfig(TaxdooConfig::TAXDOO_TRANSACTION_SYNC, 1, 'default', 0);
        $this->reinitableConfig->reinit();
        $this->messageManager->addSuccessMessage(__('Transaction sync is now enabled.'));
        $this->_redirect('adminhtml/system_config/edit', ['section' => 'tax']);
    }
}
