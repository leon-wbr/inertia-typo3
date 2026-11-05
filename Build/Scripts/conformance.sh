#!/usr/bin/env bash
#
# The conformance harness, in the DDEV web container (`ddev conformance`):
# sets up the conformance TYPO3 project (conformance/, its own database
# `conformance`, served on port 8090), builds the official Inertia clients
# and runs the Playwright suite against them.
#
#   ddev conformance                 # setup, build, every project
#   ddev conformance react http      # setup, build, only these projects
#   ddev conformance --no-setup react  # reuse the database and builds
#
# Projects: http (server rules), react, vue, svelte (official clients).
# The matrix is written to conformance/matrix.md and matrix.json.

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT/conformance"

step() { printf '\n==> %s\n' "$*"; }

setup=1
if [ "${1:-}" = "--no-setup" ]; then
  setup=0
  shift
fi

if [ "$setup" = "1" ]; then
  step "composer install"
  composer install --no-interaction --no-progress

  step "pnpm install"
  pnpm install --frozen-lockfile

  step "Reset the database"
  mysql -uroot -proot -hdb -e 'DROP DATABASE IF EXISTS conformance; CREATE DATABASE conformance; GRANT ALL ON conformance.* TO "db"@"%";'

  step "typo3 setup"
  rm -f config/system/settings.php
  TYPO3_DB_DRIVER=mysqli \
  TYPO3_DB_HOST=db \
  TYPO3_DB_PORT=3306 \
  TYPO3_DB_DBNAME=conformance \
  TYPO3_DB_USERNAME=db \
  TYPO3_DB_PASSWORD=db \
  TYPO3_SETUP_ADMIN_USERNAME=admin \
  TYPO3_SETUP_ADMIN_PASSWORD='Password1!' \
  TYPO3_SETUP_ADMIN_EMAIL='admin@example.com' \
  TYPO3_PROJECT_NAME='Inertia conformance' \
  TYPO3_SERVER_TYPE=apache \
    vendor/bin/typo3 setup --no-interaction --force

  step "Case pages"
  mysql -udb -pdb -hdb conformance < fixtures/pages.sql
  vendor/bin/typo3 extension:setup

  for client in react vue svelte; do
    if [ -d "clients/$client" ]; then
      step "Build the $client client"
      CLIENT="$client" pnpm exec vite build
    fi
  done

  vendor/bin/typo3 cache:flush
fi

step "Typecheck"
pnpm typecheck

step "Playwright"
projects=()
for project in "$@"; do
  projects+=("--project=$project")
done
pnpm test "${projects[@]}"
