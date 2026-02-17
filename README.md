# Global Payments Version Checker for Magento 2

https://github.com/globalpayments/magento2-2.0-plugin

This repository contains a helper module for Magento 2 that:

- Checks for available updates of the Global Payments payment gateway module
- Displays admin notifications when new versions are available
- Allows one‑click updates of:
  - The Global Payments payment gateway module
  - The Version Checker helper module itself

The module uses the GitHub API and raw GitHub endpoints to discover the latest versions.

## Features

- Detects the currently installed version of the Global Payments module from its `composer.json`
- Fetches the latest available version from a configurable GitHub repository
- Caches version information to reduce external calls
- Shows clear messages in the Magento admin:
  - When an update is available
  - When everything is up to date
- Provides a dedicated admin page:
  - `System → T‑ZQA eCom → Global Payments Module Status`
- Provides two update buttons:
  - Update Global Payments payment gateway
  - Update the Version Checker module itself
- Includes a cron job to refresh cached version information

## Requirements

- Magento 2
- PHP 7.4 or higher
- Shell access on the Magento server
- `git` available on the server path
- Ability for PHP to execute shell commands (`shell_exec`) and run:
  - `git clone`
  - `rm`, `cp`
  - `php bin/magento setup:upgrade`
  - `php bin/magento cache:clean`

## Module Structure (overview)

Main module namespace: `Vendor_VersionChecker`

Key parts:

- Admin controller:
  - `Vendor\VersionChecker\Controller\Adminhtml\Check\Index`
  - Handles the status page and both update actions
- Admin block and template:
  - `Vendor\VersionChecker\Block\Adminhtml\Check\Status`
  - `view/adminhtml/templates/check/status.phtml`
- Version service:
  - `Vendor\VersionChecker\Model\Service\VersionService`
  - Reads installed versions and queries GitHub
- System message:
  - `Vendor\VersionChecker\Model\System\Message\UpdateNotification`
  - Displays global admin notifications when updates are available
- Cron:
  - `Vendor\VersionChecker\Cron\RefreshLatestVersion`

## Configuration

Configuration path in Magento admin:

- `Stores → Configuration → T‑ZQA eCom → Global Payments Version Checker` (or equivalent group)

Main options (names may vary slightly depending on your integration):

- Enable module
- Target module name (default: `GlobalPayments_PaymentGateway`)
- GitHub repository owner (default: `globalpayments`)
- GitHub repository name (default: `magento2-2.0-plugin`)
- Cache lifetime in hours

The self‑update functionality for `Vendor_VersionChecker` uses fixed constants in `VersionService`:

- `SELF_MODULE_NAME = 'Vendor_VersionChecker'`
- `SELF_REPO_OWNER = 'RyanoPL'`
- `SELF_REPO_NAME = 'vendor'`

## How the Update Buttons Work

On the status page you will see up to two warning messages with buttons:

1. **Global Payments update**
   - Compares installed version of the Global Payments module with the latest tag from the configured GitHub repository
   - If an update is available, it shows:
     - Current version
     - Latest available version
   - When you click the update button:
     - The module clones the target repo at the selected tag into a temporary directory
     - Copies the module files into `app/code/GlobalPayments/PaymentGateway`
     - Runs `bin/magento setup:upgrade`
     - Cleans the cache

2. **Version Checker self‑update**
   - Compares the installed Version Checker version with the latest version discovered in the self‑repo
   - When you click the update button:
     - The module clones the `SELF_REPO_OWNER/SELF_REPO_NAME` repository to a temporary directory
     - Locates the `Vendor/VersionChecker` module within that repository
     - Copies its contents into `/magento/app/code/Vendor/VersionChecker`
     - Runs `bin/magento setup:upgrade`
     - Cleans the cache

All steps are executed on the server side using `shell_exec`. Any errors during cloning, copying, or running Magento CLI are reported back in the admin as an error message containing the relevant log output.

## Installation (high‑level)

1. Copy the `Vendor/VersionChecker` module into:

   - `<MAGENTO_ROOT>/app/code/Vendor/VersionChecker`

2. Enable the module:

   ```bash
   php bin/magento module:enable Vendor_VersionChecker
   php bin/magento setup:upgrade
   php bin/magento cache:clean
   ```

3. Configure the module in the Magento admin:

   - Enable it
   - Set the correct target module and GitHub repository, if you are using a fork

4. Navigate to:

   - `System → T‑ZQA eCom → Global Payments Module Status`

to check the current and latest versions and run updates.

## Security and Safety Notes

- This module executes shell commands on the server. It should only be used in environments where:
  - You trust the GitHub repository being cloned
  - Server permissions are configured correctly
- Always review changes and test in a staging environment before using the update buttons on production.
