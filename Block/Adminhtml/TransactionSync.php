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

namespace Taxdoo\VAT\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;
use Taxdoo\VAT\Helper\Data as TaxdooHelper;

class TransactionSync extends PopupField
{
    /**
     * @var string
     */
    // @codingStandardsIgnoreStart
    protected $_template = 'Taxdoo_VAT::transaction_sync.phtml';
    // @codingStandardsIgnoreEnd

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    protected $backendUrl;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Taxdoo\VAT\Helper\Data
     */
    protected $helper;

    /**
     * @param Context $context
     * @param UrlInterface $backendUrl
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlInterface $backendUrl,
        TaxdooHelper $helper,
        array $data = []
    ) {
        $this->scopeConfig = $context->getScopeConfig();
        $this->backendUrl = $backendUrl;
        $this->helper = $helper;
        parent::__construct($context, $backendUrl, $data);
    }

    /**
     * Get the element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return parent::_getElementHtml($element) . $this->_toHtml();
    }

    /**
     * Transaction sync enabled check
     *
     * @return bool
     */
    public function isEnabled()
    {
        $scopeCode = $this->request->getParam(ScopeInterface::SCOPE_WEBSITE, 0);

        if ($scopeCode) {
            return $this->helper->isTransactionSyncEnabled($scopeCode, ScopeInterface::SCOPE_WEBSITE);
        }

        return $this->helper->isTransactionSyncEnabled();
    }
}
