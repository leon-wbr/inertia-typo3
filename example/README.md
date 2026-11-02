# Example project

A TYPO3 14.3 project that runs the adapter (installed from `..`) with the example sitepackage (installed from `packages/sitepackage`), and holds the verification suite. The DDEV configuration lives in the repository root; see the [main README](../README.md) for the quickstart.

| Path | |
|---|---|
| `composer.json` | TYPO3 system extensions the example uses, the adapter and the sitepackage (path repositories), vite-asset-collector and the PHP test tooling |
| `packages/sitepackage` | The sitepackage: root view, shared props, page components and the React frontend. Start your own project from it |
| `package.json` | React, Inertia, Vite and the frontend tooling (pnpm), for the e2e tests and the sitepackage alike |
| `eslint.config.js`, `tsconfig.json`, `.prettierrc.json` | One lint, typecheck and format setup for the whole example, sitepackage included |
| `vite.config.ts` | Upstream vite-plugin-typo3, React, Tailwind, and the dev server flag file |
| `config/sites/main` | The site, in English (`/`) and German (`/de/`) |
| `config/system/additional.php` | Database and dev server settings; `settings.php` is written by `typo3 setup` |
| `fixtures/content.sql` | Pages and content, loaded by `ddev setup-example` |
| `e2e/` | Playwright tests, run by `ddev verify` against the production build |

## Commands

| Command | |
|---|---|
| `ddev setup-example` | Install dependencies and recreate the site from scratch |
| `ddev vite` | Vite dev server with hot module replacement |
| `ddev exec -d /var/www/html/example pnpm build` | Production build |
| `ddev verify` | The whole verification suite |
| `ddev outdated` | Outdated Composer and pnpm dependencies |

## Why some dependencies look unusual

- `.pnpmfile.cjs`: TypeScript 7 has no JavaScript compiler API, which typescript-eslint 8 still needs. Type checking runs TypeScript 7; the lint toolchain gets the TypeScript 6 API package.
- `resolve.preserveSymlinks: false` in `vite.config.ts`: vite-plugin-typo3 defaults it to `true`, which breaks pnpm's symlinked `node_modules`.
