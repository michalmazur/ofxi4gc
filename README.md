# OFXI4GC

OFX Importer for GnuCash

OFXI4GC is a PHP tool designed to import transaction data from OFX files into GnuCash SQlite 3 database.

The primary purpose of this tool is to import credit card transactions from financial institutions into GnuCash. GnuCash
is capable of storing data in various formats, but only the SQLite3 format is supported by this tool. There are no plans
to add support for other formats.

## Usage

This tool was developed using Docker on a Mac. I rely on docker-compose during development, and I continue to use
docker-compose to run this tool. However, with minimal changes to `index.php`, it should be usable in any environment
with PHP 8.2 installed as long as the dependencies are also installed.

1. Clone this repository.
2. Install the dependencies.
   ```
   docker-compose run php-cli composer install --no-dev
   ```
4. Run the tool. The general format of the command is described below:
   ```
   docker-compose run \
     -v <path_to_ofx_file>:/app/input.ofx \
     -v <path_to_gnucash_file>:/app/database.gnucash \
     php-cli php src/index.php \
     <debit_account_name> \
     <debit_account_type> \
     <credit_account_name> \
     <credit_account_type>
   ```

* `<path_to_ofx_file>` Path to OFX file to import (example: `~/database.gnucash`)
* `<path_to_gnucash_file>` Path to GnuCash SQLite 3 file (example: `~/Downloads/CreditCard1111.ofx`)
* `<debit_account_name>` Debit account name (example: `Card1111`)
* `<debit_account_type>` Debit account type (example: `CREDIT`)
* `<credit_account_name>` Credit account name (example: `Miscellaneous`)
* `<credit_account_type>` Credit account type (example: `EXPENSE`)

## Testing

The test suite can be executed using the following commands:

```
docker-compose run php-cli composer install
docker-compose run php-cli vendor/bin/phpunit
```
