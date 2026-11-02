#!/usr/bin/env bash
#
# The whole verification suite, in the DDEV web container (`ddev verify`):
# setup from scratch, PHP style and static analysis, PHPUnit, frontend lint
# and typecheck, a production build and the Playwright e2e tests against
# the site serving that build.

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT/example"

step() { printf '\n==> %s\n' "$*"; }

if [ -f var/vite-dev-server ]; then
  echo "The Vite dev server is running (var/vite-dev-server). Stop it: verify tests the production build." >&2
  exit 1
fi

step "Setup"
"$ROOT/Build/Scripts/setup-example.sh"

step "composer validate"
composer validate --no-check-publish --no-interaction
composer validate --no-check-publish --no-interaction "$ROOT/composer.json"

step "PHP CS Fixer"
vendor/bin/php-cs-fixer check --config="$ROOT/.php-cs-fixer.dist.php" --show-progress=none

step "PHPStan"
vendor/bin/phpstan analyse --configuration="$ROOT/phpstan.neon" --no-progress --memory-limit=1G

step "PHPUnit: unit tests"
vendor/bin/phpunit --configuration "$ROOT/Build/phpunit/UnitTests.xml"

step "PHPUnit: functional tests"
vendor/bin/phpunit --configuration "$ROOT/Build/phpunit/FunctionalTests.xml"

step "Inertia parity (Build/Parity/parity.json against the installed @inertiajs/core)"
node --test "$ROOT/Build/Scripts/"
node "$ROOT/Build/Scripts/parity.mjs" local

step "ESLint"
pnpm lint

step "Prettier"
pnpm format:check

step "TypeScript"
pnpm typecheck

step "Production build"
rm -rf public/_assets/vite
pnpm build
vendor/bin/typo3 cache:flush

step "Playwright e2e"
pnpm test:e2e

printf '\n==> All checks passed\n'
