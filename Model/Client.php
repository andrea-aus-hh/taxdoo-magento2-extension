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

namespace Taxdoo\VAT\Model;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

class Client
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $storeZip;

    /**
     * @var string
     */
    protected $storeRegionCode;

    /**
     * @var bool
     */
    protected $showResponseErrors;

    /**
     * @var \Taxdoo\VAT\Helper\Data
     */
    protected $tdHelper;

    /**
     * @var TaxdooConfig
     */
    protected $taxdooConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param RegionFactory $regionFactory
     * @param \Taxdoo\VAT\Helper\Data $tdHelper
     * @param TaxdooConfig $taxdooConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RegionFactory $regionFactory,
        \Taxdoo\VAT\Helper\Data $tdHelper,
        \Taxdoo\VAT\Model\Logger $logger,
        TaxdooConfig $taxdooConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->regionFactory = $regionFactory;
        $this->tdHelper = $tdHelper;
        $this->taxdooConfig = $taxdooConfig;
        $this->apiKey = $this->taxdooConfig->getApiKey();
        $this->storeZip = trim($this->scopeConfig->getValue('shipping/origin/postcode'));
        $this->logger = $logger->setFilename(TaxdooConfig::TAXDOO_CLIENT_LOG);
    }

    /**
     * Perform a GET request
     *
     * @param string $resource
     * @param array $errors
     * @return array
     */
    public function getResource($resource, $errors = [])
    {
        $client = $this->getClient($this->_getApiUrl($resource));
        return $this->_getRequest($client, $errors);
    }

    /**
     * Perform a POST request
     *
     * @param string $resource
     * @param array $data
     * @param array $errors
     * @return array
     */
    public function postResource($resource, $data, $errors = [])
    {
        $client = $this->getClient($this->_getApiUrl($resource), \Zend_Http_Client::POST);
        $client->setRawData(json_encode($data), 'application/json');

        return $this->_getRequest($client, $errors);
    }

    /**
     * Perform a PUT request
     *
     * @param string $resource
     * @param int $resourceId
     * @param array $data
     * @param array $errors
     * @return array
     */
    public function putResource($resource, $resourceId, $data, $errors = [])
    {
        $resourceUrl = $this->_getApiUrl($resource);// . '/' . $resourceId;
        $client = $this->getClient($resourceUrl, \Zend_Http_Client::PUT);
        $client->setRawData(json_encode($data), 'application/json');
        return $this->_getRequest($client, $errors);
    }

    /**
     * Perform a DELETE request
     *
     * @param string $resource
     * @param int $resourceId
     * @param array $errors
     * @return array
     */
    public function deleteResource($resource, $resourceId, $errors = [])
    {
        $resourceUrl = $this->_getApiUrl($resource) . '?ids=' . $resourceId;
        $client = $this->getClient($resourceUrl, \Zend_Http_Client::DELETE);
        return $this->_getRequest($client, $errors);
    }

    /**
     * Set API token for client requests
     *
     * @param string $key
     * @return void
     */
    public function setApiKey($key)
    {
        $this->apiKey = $key;
    }

    public function checkApiKey()
    {
      try {
        $response = $this->getResource('account');
        return $response;
      } catch (LocalizedException $e) {
        return False;
      }
    }

    /**
     * @param bool $toggle
     * @return void
     */
    public function showResponseErrors($toggle)
    {
        $this->showResponseErrors = $toggle;
    }

    /**
     * Get HTTP Client
     *
     * @param string $url
     * @param string $method
     * @return \Zend_Http_Client $client
     */
    private function getClient($url, $method = \Zend_Http_Client::GET)
    {
        // @codingStandardsIgnoreStart
        $client = new \Zend_Http_Client($url, ['timeout' => 30]);
        // @codingStandardsIgnoreEnd
        $client->setUri($url);
        $client->setMethod($method);
        $client->setConfig([
            'useragent' => $this->tdHelper->getUserAgent(),
            'referer' => $this->tdHelper->getStoreUrl()
        ]);
        $client->setHeaders([
            'AuthToken' => $this->apiKey,
        ]);
        return $client;
    }

    /**
     * Get HTTP request
     *
     * @param \Zend_Http_Client $client
     * @param array $errors
     * @return array
     * @throws LocalizedException
     */
    private function _getRequest($client, $errors = [])
    {

        try {
            $response = $client->request();

            if ($response->isSuccessful()) {
                $json = $response->getBody();
                return json_decode($json, true);
            } else {
                $this->_handleError($errors, $response);
            }
        } catch (LocalizedException $e) {
          throw $e;
        }

    }

    /**
     * Get Taxdoo API URL
     *
     * @param string $resource
     * @return string
     */
    private function _getApiUrl($resource)
    {
        $apiUrl = $this->taxdooConfig->getApiUrl();

        switch ($resource) {
            case 'account':
              $apiUrl .= '/account';
              break;
            case 'orders':
                $apiUrl .= '/orders';
                break;
            case 'refunds':
                $apiUrl .= '/refunds';
                break;
        }

        return $apiUrl;
    }


    /**
     * Handle API errors and throw exception
     *
     * @param array $customErrors
     * @param \Zend_Http_Response $response
     * @return void
     * @throws LocalizedException
     */
    private function _handleError($customErrors, $response)
    {
        $errors = $this->_defaultErrors() + $customErrors;
        $statusCode = $response->getStatus();

        if (isset($errors[$statusCode])) {
            throw new LocalizedException($errors[$statusCode]);
        }

        if ($this->showResponseErrors) {
            throw new LocalizedException(__($response->getBody()));
        }

        throw new LocalizedException($errors['default']);
    }

    /**
     * Return default API errors
     *
     * @return array
     */
    private function _defaultErrors()
    {
        // @codingStandardsIgnoreStart
        return [
            '401' => __('Taxdoo Authentication failed.', TaxdooConfig::TAXDOO_API_URL),
            '403' => __('Your Taxdoo API token is invalid. Please review your Taxdoo account at %1.', TaxdooConfig::TAXDOO_API_URL),
            '404' => __('Not found -- Your request path is wrong', TaxdooConfig::TAXDOO_API_URL),
            '400' => __('Bad request', TaxdooConfig::TAXDOO_API_URL),
            '429' => __("Too many requests. Are you trying DOS or not respecting Taxdoo's Rate Limits", TaxdooConfig::TAXDOO_API_URL),
            '500' => __('Internal Server Error. Get in touch with Taxdoo',TaxdooConfig::TAXDOO_API_URL),
            '503' => __('Service unavailable. Please try again later'),
            'default' => __('Could not connect to Taxdoo.')
        ];
        // @codingStandardsIgnoreEnd
    }
}
