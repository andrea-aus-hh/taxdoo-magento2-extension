# Magento 2 Taxdoo Plugin

Attempting to write a Taxdoo plugin for Magento 2 shops. Its features are at the moment the basic ones: backfilling old orders and refunds, automatically syncing new orders and refunds, as soon as they are closed. If someone with a real Magento shop wants to experiment and give feedback, I'm more than happy. The plugin supports Taxdoo's sandbox mode, so that your production calculations can stay safe.

This code is a fork of the open source [Magento 2 Taxjar plugin](https://github.com/taxjar/taxjar-magento2-extension), adapted to work with its European epigone Taxdoo. Being a derivative product, it's released under the same license, the [Open Software License 3.0](https://opensource.org/licenses/OSL-3.0) (OSL-3.0).

## Getting Started

Download the extension as a ZIP file from this repository. Unzip the archive and upload the files to `/app/code/Taxdoo/VAT`. After uploading, run the following [Magento CLI](http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands.html) commands:

```
bin/magento module:enable Taxdoo_VAT --clear-static-content
bin/magento setup:upgrade
bin/magento setup:di:compile
```

These commands will enable the Taxdoo extension, perform necessary database updates, and re-compile your Magento store.

Navigate to Stores->Configuration->Tax to enable the module and enter your API Token. It will give positive feedback if the Token is correct. Turn on Sandbox Mode if you don't want to risk dirtying your existing Taxdoo production data.
Make sure to add an Origin shipping address under Stores->Configuration, and then Sales->Shipping Settings.

You can backfill old orders and credit memos. If you activate Transaction Sync, all new complete/closed orders and credit memos will be automatically synced - that is if your API Token is correct.

## Tests

I'm still in the process of adapting the original TaxJar integration tests for this Taxdoo version. Stay tuned.

## License

Taxdoo's Magento 2 module is released under the [Open Software License 3.0](https://opensource.org/licenses/OSL-3.0) (OSL-3.0).

## Support

If you find a bug in this extension, or you want to request a new feature, don't be afraid to open a new issue. Please notice that this extension is not being developed by Taxdoo and is not officially supported by the company. It's been made for self-educational purposes and fun.
