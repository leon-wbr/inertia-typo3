> **⚠️ EXPERIMENTAL STATUS**: This package is in early experimental stage. It is not recommended for production use yet. Expect breaking changes, limited debugging capabilities, and ongoing significant architectural changes. Feel free to try it out and provide feedback, but use with caution.

# TYPO3 Inertia Sitepackage

An example TYPO3 sitepackage with an [Inertia.js](https://inertiajs.com/) 3, React 19 and Vite 8 frontend, built on the [TYPO3 Inertia adapter](https://github.com/leon-wbr/inertia-typo3). TYPO3 keeps routing, languages, access and the page cache; React renders the pages.

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
| [leon-wbr/inertia](https://github.com/leon-wbr/inertia-typo3) | current `main` |
| Node.js | 26 (LTS), with pnpm 12 |

## Quickstart

The fastest way to see it running is the example project in the adapter repository, which installs this sitepackage:

```sh
git clone https://github.com/leon-wbr/inertia-typo3.git
cd inertia-typo3
ddev start
ddev setup-example
ddev launch
```

## Installation in your own project

Neither package is on Packagist yet, so add both repositories to your project's `composer.json` and require them:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/leon-wbr/inertia-typo3.git" },
    { "type": "vcs", "url": "https://github.com/leon-wbr/inertia-sitepackage.git" }
  ]
}
```

```sh
composer require leon-wbr/inertia:@dev leon-wbr/inertia-sitepackage:@dev
```

Add the site set `leon-wbr/inertia-sitepackage` to your site configuration (`dependencies:` in `config/sites/<site>/config.yaml`). It pulls in the adapter's set and fluid_styled_content.

The frontend is built by Vite in your project root, with [vite-plugin-typo3](https://github.com/s2b/vite-plugin-typo3) collecting the entry point from `Configuration/ViteEntrypoints.json`. Install the runtime and build dependencies there:

```sh
pnpm add @inertiajs/react react react-dom html-react-parser tailwindcss @tailwindcss/vite
pnpm add -D vite vite-plugin-typo3 @vitejs/plugin-react
```

and create a `vite.config.ts`:

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

`pnpm vite` starts the dev server, `pnpm vite build` writes the production build to `public/_assets/vite/`, where vite-asset-collector finds it. The example project's `vite.config.ts` also shows how to switch TYPO3 between the dev server and the build automatically.

## Development

The frontend source is type-checked and linted in this repository:

```sh
pnpm install
pnpm verify   # ESLint, Prettier, TypeScript 7
```

The PHP code, the build and the end-to-end tests run in the adapter repository's `ddev verify`, against this package as the example project installs it.

## License

GNU General Public License v3.0. See [LICENSE](LICENSE).
