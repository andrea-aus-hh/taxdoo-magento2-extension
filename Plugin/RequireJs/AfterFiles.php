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

namespace Taxdoo\VAT\Plugin\RequireJs;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\RequireJs\Config\File\Collector\Aggregated;
use Magento\Theme\Model\Theme;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

class AfterFiles
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param Aggregated $subject
     * @param array $result
     * @param Theme $theme
     * @return mixed
     * @throws LocalizedException
     */
    public function afterGetFiles(
        Aggregated $subject,
        $result,
        Theme $theme = null
    ) {
        $isEnabled = False; //$this->scopeConfig->getValue(TaxdooConfig::TAXDOO_ADDRESS_VALIDATION);
        $areaCode = '';

        try {
            if (!is_null($theme)) {
                $areaCode = $theme->getArea();
            }
        } catch (LocalizedException $e) {
            // no-op
        }

        // If address validation is disabled, remove frontend RequireJs dependencies
        if (!$isEnabled && $areaCode == 'frontend') {
            foreach ($result as $key => &$file) {
                if ($file->getModule() == 'VAT') {
                    unset($result[$key]);
                }
            }
        }

        return $result;
    }
}
