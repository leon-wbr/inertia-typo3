#!/usr/bin/env bash
#
# Installs dependencies and (re)creates the example site: database, TYPO3
# setup, pages and content. Runs in the DDEV web container
# (`ddev setup-example`, also step 1 of `ddev verify`).

set -euo pipefail
cd "$(dirname "$0")/../../example"

echo "==> composer install"
composer install --no-interaction --no-progress

echo "==> pnpm install"
pnpm install --frozen-lockfile

echo "==> Reset the database"
mysql -uroot -proot -hdb -e 'DROP DATABASE IF EXISTS db; CREATE DATABASE db; GRANT ALL ON db.* TO "db"@"%";'

echo "==> typo3 setup"
rm -f config/system/settings.php
TYPO3_DB_DRIVER=mysqli \
TYPO3_DB_HOST=db \
TYPO3_DB_PORT=3306 \
TYPO3_DB_DBNAME=db \
TYPO3_DB_USERNAME=db \
TYPO3_DB_PASSWORD=db \
TYPO3_SETUP_ADMIN_USERNAME=admin \
TYPO3_SETUP_ADMIN_PASSWORD='Password1!' \
TYPO3_SETUP_ADMIN_EMAIL='admin@example.com' \
TYPO3_PROJECT_NAME='TYPO3 + Inertia' \
TYPO3_SERVER_TYPE=apache \
  vendor/bin/typo3 setup --no-interaction --force

echo "==> Pages and content"
mysql -udb -pdb -hdb db < fixtures/content.sql

vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush

echo "==> Ready: ${DDEV_PRIMARY_URL:-https://inertia-typo3.ddev.site} (backend: /typo3, admin / Password1!)"
