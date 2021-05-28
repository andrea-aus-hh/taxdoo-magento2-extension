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

class Notification implements \Magento\Framework\Notification\MessageInterface
{
  public function __construct(
      \Taxdoo\VAT\Model\ClientFactory $clientFactory
  ) {
      $this->clientFactory = $clientFactory;

      $this->client = $this->clientFactory->create();
      $this->client->showResponseErrors(true);
  }

   public function getIdentity()
   {
       return 'taxdoo-api-token-refused';
   }
   public function isDisplayed()
   {
       $response = $this->client->checkApiKey();
       if ($response) {
         return false;
       } else {
         return true;
       }
   }
   public function getText()
   {
       // message text

       return "Your API Token wasn't accepted by Taxdoo. Transactions and returns won't be synchronized.";
   }
   public function getSeverity()
   {

       return self::SEVERITY_MAJOR;
   }
}
