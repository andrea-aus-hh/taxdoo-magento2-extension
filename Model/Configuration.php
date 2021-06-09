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

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Configuration
{
    const TAXDOO_VERSION              = '0.0.1';
    const TAXDOO_API_URL              = 'https://api.taxdoo.com';
    const TAXDOO_SANDBOX_API_URL      = 'https://sandbox-api.taxdoo.com';
    const TAXDOO_APIKEY               = 'tax/taxdoo/apikey';
    const TAXDOO_SANDBOX_APIKEY       = 'tax/taxdoo/apikey';
    const TAXDOO_ENABLED              = 'tax/taxdoo/enabled';
    const TAXDOO_LAST_UPDATE          = 'tax/taxdoo/last_update';
    const TAXDOO_SANDBOX_ENABLED      = 'tax/taxdoo/sandbox';
    const TAXDOO_STATES               = 'tax/taxdoo/states';
    const TAXDOO_TRANSACTION_SYNC     = 'tax/taxdoo/transactions';
    const TAXDOO_DEBUG                = 'tax/taxdoo/debug';
    const TAXDOO_DEFAULT_LOG          = 'default.log';
    const TAXDOO_CALCULATIONS_LOG     = 'calculations.log';
    const TAXDOO_TRANSACTIONS_LOG     = 'transactions.log';
    const TAXDOO_CLIENT_LOG           = 'client.log';
    const TAXDOO_ADDRVALIDATION_LOG   = 'address_validation.log';
    const TAXDOO_CUSTOMER_LOG         = 'customers.log';
    const TAXDOO_TAXABLE_TAX_CODE     = '11111';
    const TAXDOO_EXEMPT_TAX_CODE      = '99999';
    const TAXDOO_MAGENTO_IDENTIFIER   = 'MAG';
    const TAXDOO_EMAIL                = 'tax/taxdoo/email';

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $resourceConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Config $resourceConfig
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Config $resourceConfig,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resourceConfig = $resourceConfig;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Returns the base API url
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->isSandboxEnabled() ? self::TAXDOO_SANDBOX_API_URL : self::TAXDOO_API_URL;
    }

    /**
     * Returns the scoped API token
     *
     * @param int $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return preg_replace('/\s+/', '', $this->scopeConfig->getValue(
            $this->isSandboxEnabled() ? self::TAXDOO_SANDBOX_APIKEY : self::TAXDOO_APIKEY,
            is_null($storeId) ? ScopeConfigInterface::SCOPE_TYPE_DEFAULT : ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * Checks if sandbox mode is enabled
     *
     * @return bool
     */
    public function isSandboxEnabled() {
        return (bool) $this->scopeConfig->getValue(self::TAXDOO_SANDBOX_ENABLED);
    }

    /**
     * Store config
     *
     * @param string $path
     * @param string $value
     * @return void
     */
    private function _setConfig($path, $value)
    {
        $this->resourceConfig->saveConfig($path, $value, 'default', 0);
    }
}
