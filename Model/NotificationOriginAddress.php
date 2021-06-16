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
 * @copyright  Copyright (c) 2017 Taxdoo. Taxdoo is a trademark of TPS Unlimited, Inc. (http://www.Taxdoo.com)
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace Taxdoo\VAT\Model;

use Magento\Store\Model\ScopeInterface;
use \Magento\Framework\UrlInterface;

class NotificationOriginAddress implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlInterface;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $urlInterface
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->urlInterface = $urlInterface;
    }

    public function getIdentity()
    {
        return 'taxdoo-origin-address-missing';
    }

    public function isDisplayed()
    {
        $originCountry = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_COUNTRY_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $originPostCode = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_POSTCODE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $originRegion = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_REGION_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $originCity = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_CITY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $originStreet = $this->scopeConfig->getValue(
            'shipping/origin/street_line1',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (empty($originCountry) ||
            empty($originPostCode) ||
            empty($originRegion) ||
            empty($originCity) ||
            empty($originStreet)) {
             return true;
        }
        return false;
    }

    public function getText()
    {
        $section = 'adminhtml/system_config/edit/section/shipping';
        $url = $this->urlInterface->getUrl($section);
        // @codingStandardsIgnoreStart
        return __("Your origin shipping address is partially or completely missing, your orders will not synchronize with Taxdoo. Please <a href=\"".$url."\">check the configuration</a>");
        // @codingStandardsIgnoreEnd
    }
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }
}
