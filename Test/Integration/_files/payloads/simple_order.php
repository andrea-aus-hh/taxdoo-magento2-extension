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

 use Taxdoo\VAT\Model\Configuration as TaxdooConfig;

$randomTransactionNumber = 'TD-Integration-Test-Order-' . rand(0, 100000);
$now = new \DateTime(date('Y-m-d H:i:s'));
return [
  'type' => 'Sale',
  'channel' => [
    'identifier' => TaxdooConfig::TAXDOO_MAGENTO_IDENTIFIER,
    'transactionNumber' => $randomTransactionNumber
  ],
  'paymentDate' => $now->format(\DateTime::RFC3339),
  'sentDate' => $now->format(\DateTime::RFC3339),
  'deliveryAddress' => [
    'fullName' => "Andrea Lazzaretti",
    'street' => "Breiter Gang 6",
    'zip' => "20355",
    'city' => "Hamburg",
    'state' => "Hamburg",
    'country' => "DE"
  ],
  'billingAddress' => [
    'fullName' => "Pepito Sbezzeguti",
    'street' => "Via F. Signorelli, 4",
    'zip' => "42018",
    'city' => "San Martino in Rio",
    'state' => "Emilia Romagna",
    'country' => "IT"
  ],
  'senderAddress' => [
      'country' => "DE",
      'zip' => "20253",
      'state' => "Hamburg",
      'city' => "Hamburg",
      'street' => "Hoheluftchaussee 13"
  ],
  'shipping' => 5.0,
  'transactionCurrency' => 'EUR',
  'items' => [[
  'quantity' => 1,
  'productIdentifier' => '24-WG082-blue',
  'description' => 'Sprite Stasis Ball 65 cm',
  'itemPrice' => 27.0,
  'channelItemNumber' => "001",
  'discount' => 0.0
  ]],
  'paymentChannel' => "Paypal",
  'paymentNumber' => "TD-Integration-Test-Payment-0000001",
  'invoiceDate' => $now->format(\DateTime::RFC3339),
  'invoiceNumber' => "TD-Integration-Test-Invoice-0000001",
];
