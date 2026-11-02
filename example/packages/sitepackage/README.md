> **⚠️ EXPERIMENTAL STATUS**: This package is in early experimental stage. It is not recommended for production use yet. Expect breaking changes, limited debugging capabilities, and ongoing significant architectural changes. Feel free to try it out and provide feedback, but use with caution.

# TYPO3 Inertia Sitepackage

The example project's sitepackage, and a starter for your own: a TYPO3 sitepackage with an [Inertia.js](https://inertiajs.com/) 3, React 19 and Vite 8 frontend, built on the [TYPO3 Inertia adapter](../../../README.md). TYPO3 keeps routing, languages, access and the page cache; React renders the pages.

It used to live in its own repository, `leon-wbr/inertia-sitepackage`; its history is kept here.

## What it contains

- **Shared props** for every page: site title, home link, languages (with the current page in each language) and the navigation. The navigation comes from the adapter's `NavigationService`, which respects translations, hidden pages, start and end times, access groups and workspaces.
- **Page components** in `Resources/Private/src/Pages`, chosen by the page's frontend layout (`pages.layout`):
  - `Default`: the page's content elements;
  - `Features` (layout 1): deferred, optional and merge props;
  - `Contact` (layout 2): a form submitted with Inertia's `<Form>`, answered with a 303 redirect.
- **Content elements**: `header` has a React component. Every other type is rendered from the HTML TYPO3 produces (fluid_styled_content), with internal links turned into Inertia links.
- **Root view** `Resources/Private/Templates/App.html`: the Inertia page script plus the Vite entry point via [vite-asset-collector](https://github.com/s2b/vite-asset-collector).

## Requirements

| | Version |
|---|---|
| TYPO3 | 14.3 |
| PHP | 8.2 to 8.5 |
| [leon-wbr/inertia](../../../README.md) | the version in this repository |
| Node.js | 26 (LTS), with pnpm 12 |

## Quickstart

Run it in the example project:

```sh
git clone https://github.com/leon-wbr/inertia-typo3.git
cd inertia-typo3
ddev start
ddev setup-example
ddev launch
```

## Using it in your own project

Copy this directory into your project's `packages/` directory and require it through a Composer path repository. The [main README](../../../README.md#starting-from-the-example-sitepackage) has the steps.

The frontend is built by Vite in your project root, with [vite-plugin-typo3](https://github.com/s2b/vite-plugin-typo3) collecting the entry point from `Configuration/ViteEntrypoints.json`. The example's [`package.json`](../../package.json) and [`vite.config.ts`](../../vite.config.ts) are a working setup; the minimum is:

```sh
pnpm add @inertiajs/react react react-dom html-react-parser tailwindcss @tailwindcss/vite
pnpm add -D vite vite-plugin-typo3 @vitejs/plugin-react
```

```ts
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";
import typo3 from "vite-plugin-typo3";

export default defineConfig({
  plugins: [typo3(), react(), tailwindcss()],
  resolve: {
    // vite-plugin-typo3 turns this on; pnpm's node_modules layout needs it off.
    preserveSymlinks: false,
  },
});
```

`pnpm vite` starts the dev server, `pnpm vite build` writes the production build to `public/_assets/vite/`, where vite-asset-collector finds it.

## Development

The sitepackage has no tooling of its own: the example project lints, formats and type-checks it (one pnpm workspace, one ESLint, Prettier and TypeScript config), and `ddev verify` covers its PHP, the build and the end-to-end tests.

## License

GNU General Public License v3.0. See [LICENSE](LICENSE).
