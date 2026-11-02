> **⚠️ EXPERIMENTAL STATUS**: This package in early experimental stage. It is not recommended for production use yet. Expect breaking changes, limited debugging capabilities, and ongoing significant architectural changes. Feel free to try it out and provide feedback, but use with caution.

# TYPO3 Inertia Adapter

A TYPO3 extension providing the server side of [Inertia.js](https://inertiajs.com/) 3: TYPO3 keeps routing, languages, access, redirects and the page cache, and answers with Inertia page objects that a React, Vue or Svelte frontend renders.

It follows the official [inertia-laravel](https://github.com/inertiajs/inertia-laravel) 3.x adapter where TYPO3 allows.

## Features

- The Inertia 3 protocol: the `<script type="application/json">` page element, JSON responses for Inertia visits, asset versioning (409 with `X-Inertia-Location`), 303 redirects after `PUT`, `PATCH` and `DELETE`, and redirects with fragments (`X-Inertia-Redirect`).
- Partial reloads (`only`, `except`, nested paths), with deferred, optional, always and merge props, including deep merge, prepend and `matchOn`.
- Shared props, resolved lazily when a page is rendered.
- History encryption and clearing flags.
- JSON request bodies parsed for Inertia forms.
- A page plugin that renders TYPO3 pages with their content elements, through the core Record API, with TypoScript-rendered HTML as fallback.
- A navigation builder that respects translations, hidden pages, start and end times, access groups and workspaces.

## Requirements

| | Version |
|---|---|
| TYPO3 | 14.3 |
| PHP | 8.2 to 8.5 |
| Inertia.js client | 3.x |

TYPO3 13.4 is no longer supported; the earlier 13.4 proof of concept stays in the git history.

## Quickstart with DDEV

The repository contains a runnable example project in [`example/`](example/): TYPO3 14.3, the [Inertia sitepackage](https://github.com/leon-wbr/inertia-sitepackage) with a React 19 frontend, an English and a German page tree, and the verification suite.

```sh
git clone https://github.com/leon-wbr/inertia-typo3.git
cd inertia-typo3
ddev start
ddev setup-example   # composer and pnpm install, TYPO3 setup, pages and content
ddev exec -d /var/www/html/example pnpm build
ddev launch
```

The backend is at `/typo3` (user `admin`, password `Password1!`).

For frontend work, run the Vite dev server instead of the build. Start it from `example/`, because `ddev vite` runs in the matching directory inside the container:

```sh
cd example
ddev vite
```

While it runs, it writes `example/var/vite-dev-server`, and TYPO3 loads assets from the dev server with hot module replacement; flush the TYPO3 caches (`ddev exec example/vendor/bin/typo3 cache:flush`) for already cached pages. Stop it (Ctrl+C) and TYPO3 serves the production build again. If the dev server crashed instead, delete `example/var/vite-dev-server` yourself.

The DDEV project runs PHP 8.5, MariaDB 10.11 and Node.js 26 (LTS) with pnpm 12, and needs DDEV 1.25 or later.

## Verification

```sh
ddev verify
```

runs everything from scratch and exits non-zero on the first failure:

1. `composer install`, `pnpm install`, a fresh database, `typo3 setup` and the example content;
2. `composer validate`, PHP CS Fixer and PHPStan (level 8);
3. PHPUnit unit and functional tests (TYPO3 testing framework, SQLite);
4. ESLint, Prettier and the TypeScript 7 typecheck;
5. a Vite production build;
6. Playwright end-to-end tests against the site serving that build: first load, Inertia navigation, asset version mismatch, form redirects, query strings, partial reloads, deferred, optional and merge props, and the second language.

GitHub Actions runs the same command on every push and pull request. `ddev outdated` lists outdated Composer and pnpm dependencies of the example.

## Installation in your own project

The package is not on Packagist yet. Add the repository and require it:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/leon-wbr/inertia-typo3.git" }
  ]
}
```

```sh
composer require leon-wbr/inertia:@dev
```

Then:

1. Add the site set `leon-wbr/inertia` to your site (or a sitepackage set that depends on it). It renders every page through the page plugin.
2. Provide a root view: a Fluid template `App.html` in a path you add to `plugin.tx_inertia.view.templateRootPaths`. It renders the page element and your entry point:

   ```html
   <html
     xmlns:inertia="http://typo3.org/ns/LeonWbr/Inertia/ViewHelpers"
     xmlns:vite="http://typo3.org/ns/Praetorius/ViteAssetCollector/ViewHelpers"
     data-namespace-typo3-fluid="true"
   >
   <inertia:app page="{page}" />
   <vite:asset entry="EXT:my_sitepackage/Resources/Private/src/main.tsx" />
   </html>
   ```

3. Set up the client with `createInertiaApp` from `@inertiajs/react` (or the Vue or Svelte adapter).

The [Inertia sitepackage](https://github.com/leon-wbr/inertia-sitepackage) is a complete example.

## Usage

### Shared props

Replace the adapter's middleware with your own subclass, registered under the same identifier in your extension's `Configuration/RequestMiddlewares.php`:

```php
return [
    'frontend' => [
        'leon-wbr/inertia' => [
            'target' => \MyVendor\MySitepackage\Middleware\InertiaMiddleware::class,
        ],
    ],
];
```

```php
class InertiaMiddleware extends \LeonWbr\Inertia\Middleware\InertiaMiddleware
{
    public function share(ServerRequestInterface $request): array
    {
        return [
            // Closures run when a page is rendered, with the routed request.
            'navigation' => fn(ServerRequestInterface $request): array => $this->navigation->build($request),
        ];
    }
}
```

### Page component and props

Pages render the component from the site setting `inertia.component` (default `Default`) with the props `title` and `contentElements`. Listen to `BeforeInertiaPageRenderedEvent` to pick another component or add props:

```php
#[AsEventListener]
final class ProductPageListener
{
    public function __construct(private readonly InertiaService $inertia) {}

    public function __invoke(BeforeInertiaPageRenderedEvent $event): void
    {
        if ((int)$event->getPage()['layout'] !== 3) {
            return;
        }
        $event->setComponent('Products');
        $event->addProp('products', $this->inertia->merge(fn() => $this->products->page(1)));
        $event->addProp('stats', $this->inertia->defer(fn() => $this->products->stats()));
        $event->addProp('filters', $this->inertia->optional(fn() => $this->products->filters()));
    }
}
```

In your own Extbase controllers or middlewares, use `InertiaService` directly: `render()`, `location()`, `redirect()`, `share()`, `always()`, `optional()`, `defer()`, `merge()`, `deepMerge()`, `encryptHistory()` and `clearHistory()`.

### Settings

| Site setting | Default | |
|---|---|---|
| `inertia.component` | `Default` | Page component for TYPO3 pages |
| `inertia.contentElements.typedTypes` | none | CTypes your frontend renders itself; their HTML fallback is skipped |
| `inertia.contentElements.properties` | common `tt_content` fields | Fields sent as `data`; empty sends all |

The extension setting `manifestPath` (default `_assets/vite/.vite/manifest.json`) is the file whose hash is the asset version.

## Contributing

See the [Contributing Guidelines](CONTRIBUTING.md). Run `ddev verify` before opening a pull request.

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0). See the [LICENSE](LICENSE) file for details.
