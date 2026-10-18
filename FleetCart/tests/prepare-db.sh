#!/usr/bin/env bash
# Builds the scratch database used by the feature tests.
#   1. install the store once (browser/installer or `POST /install`) into $SOURCE_DB
#   2. run this script: it clones $SOURCE_DB into fleetcart_test and seeds test settings
# Credentials come from .env (DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD).
set -euo pipefail
cd "$(dirname "$0")/.."

SOURCE_DB="${1:-fleetcart_local}"
set -a; source .env; set +a

MYSQL=(mariadb -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" ${DB_PASSWORD:+-p"${DB_PASSWORD}"})
DUMP=(mariadb-dump -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" ${DB_PASSWORD:+-p"${DB_PASSWORD}"} --single-transaction)

"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS fleetcart_test; CREATE DATABASE fleetcart_test CHARACTER SET utf8mb4"
"${DUMP[@]}" "${SOURCE_DB}" | "${MYSQL[@]}" fleetcart_test

DB_DATABASE=fleetcart_test APP_ENV=testing php tests/support/seed-test-settings.php
