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

use Taxdoo\VAT\Model\Configuration as TaxdooConfig;
use \Magento\Framework\UrlInterface;

class NotificationSandbox implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var TaxdooConfig
     */
    protected $taxdooConfig;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlInterface;

    public function __construct(
        TaxdooConfig $taxdooConfig,
        \Magento\Framework\UrlInterface $urlInterface
    ) {
        $this->taxdooConfig = $taxdooConfig;
        $this->urlInterface = $urlInterface;
    }

    public function getIdentity()
    {
        return 'taxdoo-sandbox-active';
    }

    public function isDisplayed()
    {
        return $this->taxdooConfig->isSandboxEnabled();
    }

    public function getText()
    {
        $section = 'adminhtml/system_config/edit/section/tax/taxdoo';
        $url = $this->urlInterface->getUrl($section);
        // @codingStandardsIgnoreStart
        return __("The Taxdoo Sandbox mode is active. Orders and refunds are not being synchronized with your production data. To deactivate sandbox mode modify the <a href=\"".$url."\">Taxdoo configuration</a>.");
        // @codingStandardsIgnoreEnd
    }

    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
