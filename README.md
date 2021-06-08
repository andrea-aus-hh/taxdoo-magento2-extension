# Magento 2 Taxdoo Plugin

A Taxdoo extension for Magento 2 shops. Its features are at the moment the basic ones: backfilling old orders and refunds, automatically syncing new orders and refunds. It has some very obvious to-dos. Among others:

* Support for gift cards
* Support for modifications of already posted transactions through a PUT request
* B2B customers support

Since I could only test this extension on my own Magento 2 test system, I'm more than happy for every further tester. My test system can't generate real payment data, which is needed to provide Taxdoo a Payment Date for every order and refund - the code uses the Invoice Date as a placeholder, if no real payment is found. It's not clear cut to me if this is a good practice, in the case of a purchase on invoice, in which Magento per se won't know about when the money landed in the bank account and only knows when it was invoiced. Glad to get feedback on this - as on anything else.

This extension supports Taxdoo's sandbox mode, so that production data can stay safe.

This code is a fork of the open source [Magento 2 Taxjar plugin](https://github.com/taxjar/taxjar-magento2-extension), adapted to work with its European epigone Taxdoo. Being a derivative product, it's released under the same license, the [Open Software License 3.0](https://opensource.org/licenses/OSL-3.0) (OSL-3.0).

## Getting Started

Download the extension as a ZIP file from this repository, or clone it. Upload the files to `/app/code/Taxdoo/VAT` in your Magento 2 directory. After uploading, run the following [Magento CLI](http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands.html) commands:

```
bin/magento module:enable Taxdoo_VAT --clear-static-content
bin/magento setup:upgrade
bin/magento setup:di:compile
```

These commands will enable the Taxdoo extension, perform necessary database updates, and re-compile your Magento store.

To start using the extension you'll need to do the following:
1. **Add an origin shipping address** under *Stores->Configuration->Sales->Shipping Settings*. Without a complete origin address Taxdoo will reject your requests.
1. Navigate to *Stores->Configuration->Sales->Tax*, **enable the module**, **enter your API Token** and **save the configuration**. You will get feedback if the token has been accepted or rejected.
1. By default Magento won't make the **"State" field** mandatory at checkout for every nation. Since this is a required field for every Taxdoo order, it's a good idea to **make that field mandatory at checkout**, at least for EU countries. It can be easily done in the Magento configuration menu at *Stores->Configuration->General->State Option*. Otherwise, if the user decides not to include it, the sync request would be rejected by Taxdoo.
1. (optional) Turn on **sandbox mode** if you don't want to risk dirtying your existing Taxdoo production data.
1. (optional) **Activate transaction sync** if you want all new orders and credit memos to be automatically synced.
1. (optional) **Backfill your old orders and refunds**, by clicking on the button *"Sync Transaction"* and following further instructions on the interface.

Under *Sales->Orders* you can add the column "Synced to Taxdoo (or sandbox)" to the table: every order that you backfilled or automatically synced should contain a sync date. If not, please verify that the API token is correct and that you set a complete origin address. Please notice that the extension will only synchronise complete or closed orders.

If you want to disable the extension, run the following commands:

```
bin/magento module:disable Taxdoo_VAT --clear-static-content
bin/magento setup:upgrade
bin/magento setup:di:compile
```

**Please notice**: this extension is developed for self-educational purpose and for fun. It is not officially supported by Taxdoo.

## Tests

The integration tests here included, largely inherited from TaxJar, test in particular the generation of Orders/Refunds objects in many different cases, and the client. This creates some test transactions in your Taxdoo Sandbox environment and cleans them up afterwards - and requires a working Taxdoo API Token.

To run our integration tests, clone the repository into your local instance of Magento 2. You'll need an active Taxdoo API token (preferably a test account) to run these tests.

Backup or rename your existing `phpunit.xml` under `dev/tests/integration`. Copy the `phpunit.xml file` in the Taxdoo module under `app/code/Taxdoo/VAT/Test/Integration`:

```
cp app/code/Taxdoo/VAT/Test/Integration/phpunit.xml dev/tests/integration/phpunit.xml
```
Rename `install-config-mysql.php.dist` to `install-config-mysql.php` under `dev/tests/integration/etc`. Edit the settings in order to give Magento access to a MySQL database for integration tests.

Copy `Test/Integration/credentials.php.dist` to `credentials.php` in the same directory. Edit the file to add your Taxdoo API token and your Taxdoo Email Address:

```
cp app/code/Taxdoo/VAT/Test/Integration/credentials.php.dist app/code/Taxdoo/VAT/Test/Integration/credentials.php
```

Finally, run the Taxdoo test suite using PHPUnit:

```
vendor/bin/phpunit -c <Magento_Home>/dev/tests/integration/phpunit.xml --testsuite “Taxdoo”
```

Notice that the configuration flag should include the full path to `phpunit.xml`.

Deeper and broader tests are being written. Stay tuned.

## License

Taxdoo's Magento 2 module is released under the [Open Software License 3.0](https://opensource.org/licenses/OSL-3.0) (OSL-3.0).

## Support

If you find a bug in this extension, or you want to request a new feature, don't be afraid to open a new issue or get in touch with me.

Please also refer to the [Taxdoo API Documentation](https://dev.taxdoo.com/).
