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

namespace Taxdoo\VAT\Observer;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;
use \Taxdoo\VAT\Model\ClientFactory;
use Magento\Framework\Exception\LocalizedException;

class ConfigReview implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var TaxdooConfig
     */
    protected $taxdooConfig;

    /**
     * @var \Taxdoo\VAT\Model\ClientFactory
     */
    protected $clientFactory;

    /**
     * @param \Magento\Framework\App\Request\Http $request
     * @param CacheInterface $cache
     * @param ManagerInterface $eventManager
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxdooConfig $taxdooConfig
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        CacheInterface $cache,
        ManagerInterface $eventManager,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        ScopeConfigInterface $scopeConfig,
        \Taxdoo\VAT\Model\ClientFactory $clientFactory,
        TaxdooConfig $taxdooConfig,
        \Magento\Framework\Notification\NotifierInterface $notifierPool
    ) {
        $this->request = $request;
        $this->cache = $cache;
        $this->eventManager = $eventManager;
        $this->messageManager = $messageManager;
        $this->scopeConfig = $scopeConfig;
        $this->taxdooConfig = $taxdooConfig;
        $this->clientFactory = $clientFactory;
        $this->notifierPool = $notifierPool;

        $this->client = $this->clientFactory->create();
        $this->client->showResponseErrors(true);
    }

    /**
     * @param  Observer $observer
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    // @codingStandardsIgnoreStart
    public function execute(Observer $observer)
    {
        // @codingStandardsIgnoreEnd
        $configSection = $this->request->getParam('section');

        $event = $observer->getEvent();
        $eventName = $event->getName();

        if ($configSection == 'tax') {
            $enabled = $this->scopeConfig->getValue(TaxdooConfig::TAXDOO_ENABLED);

            if ($enabled) {
                $this->_reviewSandboxMode();
                $this->_checkApiKey();
            }
        }
        return $this;
    }

    /**
     * @return void
     * @SuppressWarnings(Generic.Files.LineLength.TooLong)
     */
    private function _reviewSandboxMode()
    {
        if ($this->taxdooConfig->isSandboxEnabled()) {
            // @codingStandardsIgnoreStart
            $this->messageManager->addComplexWarningMessage('tdSandboxWarning');
            // @codingStandardsIgnoreEnd
        }
    }

    private function _checkApiKey()
    {
        $response = $this->client->checkApiKey();
        if (!$response) {
            $this->messageManager->addComplexErrorMessage(
                'tdAccountResponse',
                ['accepted' => false]
            );
            return;
        }
        $this->messageManager->addComplexSuccessMessage(
            'tdAccountResponse',
            ['accepted' => true, 'response' => $response]
        );
    }
}
