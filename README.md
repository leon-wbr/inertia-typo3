> **⚠️ EXPERIMENTAL STATUS**: This package in early experimental stage. It is not recommended for production use yet. Expect breaking changes, limited debugging capabilities, and ongoing significant architectural changes. Feel free to try it out and provide feedback, but use with caution.

# TYPO3 Inertia Adapter

A TYPO3 extension providing the server side of [Inertia.js](https://inertiajs.com/) 3: TYPO3 keeps routing, languages, access, redirects and the page cache, and answers with Inertia page objects that a React, Vue or Svelte frontend renders.

It follows the official [inertia-laravel](https://github.com/inertiajs/inertia-laravel) 3.x adapter where TYPO3 allows.

## Features

- The Inertia 3 protocol: the `<script type="application/json">` page element, JSON responses for Inertia visits, asset versioning (409 with `X-Inertia-Location`), 303 redirects after `PUT`, `PATCH` and `DELETE`, and redirects with fragments (`X-Inertia-Redirect`).
- Partial reloads (`only`, `except`, nested paths), with deferred, optional, always and merge props, including deep merge, prepend and `matchOn`.
- Validation errors and error bags: flashed across the redirect in the TYPO3 frontend session (anonymous visitors included), always shared as the `errors` prop, scoped by `X-Inertia-Error-Bag`, and cleared once read.
- Flash data, sent once as the page object's `flash`.
- Once props (`onceProps`, `X-Inertia-Except-Once-Props`), with custom keys, expiry and refresh.
- Scroll props for `<InfiniteScroll>` (`scrollProps`, the merge intent header), from TYPO3's core paginators or plain arrays.
- Shared props, resolved lazily when a page is rendered, and shared once props.
- History encryption and clearing flags.
- Testing helpers for TYPO3 functional tests, like inertia-laravel's `assertInertia()`.
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

The repository contains a runnable example project in [`example/`](example/): TYPO3 14.3, the example sitepackage in [`example/packages/sitepackage`](example/packages/sitepackage/) with a React 19 frontend, an English and a German page tree, and the verification suite.

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
6. Playwright end-to-end tests against the site serving that build: first load, Inertia navigation, asset version mismatch, form redirects with validation errors and flash data, query strings, partial reloads, deferred, optional, merge and once props, infinite scroll, and the second language.

GitHub Actions runs the same command on every push and pull request. `ddev outdated` lists outdated Composer and pnpm dependencies of the example.

### Conformance with the official clients

```sh
ddev conformance
```

runs the official Inertia clients, `@inertiajs/react`, `@inertiajs/vue3` and `@inertiajs/svelte`, against a TYPO3 project of their own with this adapter. Every case of the protocol conformance list is a test, and known gaps are expected failures. The run writes a case × client matrix to `conformance/matrix.md`. GitHub Actions runs it on every push and shows the matrix in the job summary. See [conformance/README.md](conformance/README.md).

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

### Starting from the example sitepackage

[`example/packages/sitepackage`](example/packages/sitepackage/) is the example's sitepackage and a complete starter: the root view, shared props (site, languages, navigation), page components chosen by frontend layout, content elements rendered by React or from TYPO3's HTML, and a contact form. To start your own project from it:

1. Copy `example/packages/sitepackage` into your project's `packages/` directory, and rename the Composer package, the extension key and the PHP namespace if you like.
2. Add a path repository for it and require it:

   ```json
   {
     "repositories": [
       { "type": "path", "url": "packages/*" },
       { "type": "vcs", "url": "https://github.com/leon-wbr/inertia-typo3.git" }
     ]
   }
   ```

   ```sh
   composer require leon-wbr/inertia:@dev leon-wbr/inertia-sitepackage:@dev
   ```

3. Add its site set `leon-wbr/inertia-sitepackage` to your site. It pulls in the adapter's set and fluid_styled_content.
4. Build the frontend in your project root, as the example does: copy `example/package.json`, `example/vite.config.ts`, `example/tsconfig.json` and `example/eslint.config.js` (drop the Playwright parts you don't need), then `pnpm install` and `pnpm build`.

See the [sitepackage README](example/packages/sitepackage/README.md) for what it contains.

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

In your own Extbase controllers or middlewares, use `InertiaService` directly: `render()`, `location()`, `redirect()`, `share()`, `shareOnce()`, `always()`, `optional()`, `defer()`, `merge()`, `deepMerge()`, `once()`, `scroll()`, `withErrors()`, `flash()`, `encryptHistory()` and `clearHistory()`.

### Forms and validation errors

Handle the submission, flash the errors and redirect back. The errors are stored in the TYPO3 frontend user session, which works for anonymous visitors too (TYPO3 sets its session cookie once something is stored), and arrive on the next page as the `errors` prop:

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
{
    $name = trim((string)($request->getParsedBody()['name'] ?? ''));
    if ($name === '') {
        $this->inertia->withErrors(['name' => 'Please enter your name.']);
    } else {
        $this->inertia->flash('success', 'Thanks!');
    }

    return $this->inertia->redirect($request->getUri()->getPath()); // 303 See Other
}
```

- `errors` is shared with every page as an always prop (included in partial reloads), and is `{}` without errors. Errors are cleared once a page has been rendered with them.
- A field can have a list of messages; the first one is sent. Set `protected bool $withAllErrors = true;` in your middleware subclass to send them all.
- Error bags: `withErrors($errors, 'login')` stores them in a named bag. A request with `X-Inertia-Error-Bag: contact` (`<Form errorBag="contact">` or `router.post(url, data, { errorBag: 'contact' })`) gets the default bag as `errors.contact`, and the client scopes the form's errors to it.

The example's [contact form](example/packages/sitepackage/Classes/Middleware/ContactFormMiddleware.php) works this way.

### Flash data

`$this->inertia->flash('success', 'Saved')` (or an array) stores data for the next page the client renders. As in inertia-laravel 3.x, it is sent as the page object's `flash` key, not as a prop: read it with `usePage().flash`, or `router.on('flash', …)` for toasts. It is sent once and then cleared. Type it with `flashDataType` in `InertiaConfig`.

### Once props

A once prop is resolved once and then kept by the client: on later visits the client lists the once props it has in `X-Inertia-Except-Once-Props`, and the server skips them.

```php
$event->addProp('plans', $this->inertia->once(fn() => $this->plans->all()));
$event->addProp('countries', $this->inertia->once(fn() => $this->countries())->as('countries-v2')->until(3600));
$event->addProp('stats', $this->inertia->defer(fn() => $this->stats())->once());
```

- `as()` sets the key the client remembers it by (default: the prop path), `until()` sets an expiry (seconds, a `DateInterval` or a `DateTimeInterface`), `fresh()` sends it anyway.
- Deferred, optional and merge props can be once props too (`->once()`).
- Share once props with every page from your middleware's `shareOnce()` (or `InertiaService::shareOnce()`):

  ```php
  public function shareOnce(ServerRequestInterface $request): array
  {
      return ['labels' => fn() => $this->labels->forLanguage($request)];
  }
  ```

### Infinite scroll

`scroll()` makes a prop for `<InfiniteScroll>`. The page announces it in `scrollProps` (page name, previous, next and current page), and each page the client loads is appended to the items, or prepended when it scrolls up (`X-Inertia-Infinite-Scroll-Merge-Intent`).

```php
use TYPO3\CMS\Core\Pagination\ArrayPaginator;

$page = max(1, (int)($request->getQueryParams()['page'] ?? 1));

// From a TYPO3 paginator (ArrayPaginator, QueryBuilderPaginator, Extbase's
// QueryResultPaginator, …): sent as {data: [...], meta: {currentPage, lastPage}}.
$event->addProp('articles', $this->inertia->scroll(
    fn() => new ArrayPaginator($articles, $page, 10),
));

// From a list and the metadata as an array: sent as {data: [...]}.
$event->addProp('posts', $this->inertia->scroll($posts, metadata: [
    'currentPage' => $page,
    'previousPage' => $page > 1 ? $page - 1 : null,
    'nextPage' => $hasMore ? $page + 1 : null,
]));
```

```tsx
<InfiniteScroll data="articles">
  {articles.data.map((article) => <Article key={article.id} {...article} />)}
</InfiniteScroll>
```

- The client sends the page in the `page` query parameter. TYPO3 paginators don't know that name: pass `pageName:` if you use another one. Add the parameter to `$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters']`, as the example sitepackage does, so TYPO3 doesn't ask for a cHash.
- The items are under `data`; pass another wrapper key as the second argument. A metadata callback (`fn($value) => new ScrollMetadata(...)`) or any `ProvidesScrollMetadata` works too.
- `->defer()` loads the first page right after the page instead of with it.

### Testing

`LeonWbr\Inertia\Testing\InteractsWithInertia` is a trait for functional tests with typo3/testing-framework, like inertia-laravel's `assertInertia()`:

```php
use LeonWbr\Inertia\Testing\AssertableInertia;
use LeonWbr\Inertia\Testing\InteractsWithInertia;

final class ProductsTest extends FunctionalTestCase
{
    use InteractsWithInertia;

    #[Test]
    public function listsProducts(): void
    {
        $response = $this->executeFrontendSubRequest($this->inertiaRequest('https://website.local/products'));

        $this->assertInertia($response, fn(AssertableInertia $page) => $page
            ->component('Products')
            ->has('products.data', 10)
            ->where('filters.q', '')
            ->where('products.data.0.title', fn(string $title): bool => $title !== '')
            ->missing('internal')
            ->hasDeferredProps('default', ['stats'])
            ->hasMergeProps(['products.data'])
            ->hasOnceProp('categories')
            ->hasScrollProp('products', ['nextPage' => 2])
            ->hasNoErrors()
            ->version(''));
    }
}
```

- `inertiaRequest($uri)` builds an Inertia visit, `inertiaReloadRequest($uri, $component, only: [...], except: [...])` a partial reload.
- `assertInertia()` reads JSON responses and the page script of a first visit (plain `InternalRequest`).
- Props are addressed by dot paths. Also: `hasAll()`, `prop()`, `hasError()`, `hasPrependProps()`, `hasDeepMergeProps()`, `hasFlash()`, `missingFlash()`, `url()` and `toArray()`.
- For a session (errors and flash across a redirect), send the `fe_typo_user` cookie of the POST response with the next request (`withCookieParams()`); see the adapter's [own functional tests](Tests/Functional/Frontend/InertiaFeaturesTest.php).

### Settings

| Site setting | Default | |
|---|---|---|
| `inertia.component` | `Default` | Page component for TYPO3 pages |
| `inertia.contentElements.typedTypes` | none | CTypes your frontend renders itself; their HTML fallback is skipped |
| `inertia.contentElements.properties` | common `tt_content` fields | Fields sent as `data`; empty sends all |

The extension setting `manifestPath` (default `_assets/vite/.vite/manifest.json`) is the file whose hash is the asset version.

## Compared with inertia-laravel

The adapter follows [inertia-laravel](https://github.com/inertiajs/inertia-laravel) 3.x, the reference server adapter. [TODO.md](TODO.md) has the full audit against the latest Inertia releases: every feature with its source, status, tests and priority, the next steps, and how the list is kept in sync.

| Feature | inertia-laravel 3.x | This adapter |
|---|---|---|
| Page object, JSON responses, `<script>` page element | ✓ | ✓ |
| Asset versioning (409 + `X-Inertia-Location`) | ✓ | ✓ (hash of the Vite manifest) |
| 303 after `PUT`, `PATCH`, `DELETE`; fragment redirects | ✓ | ✓ |
| External redirects (`Inertia::location()`) | ✓ | ✓ `location()` |
| Shared props, lazy closures, dot keys | ✓ | ✓ |
| Partial reloads (`only`, `except`, nested paths) | ✓ | ✓ |
| Always, optional, deferred props (groups) | ✓ | ✓ |
| Merge, prepend, deep merge, `matchOn` | ✓ | ✓ |
| Once props (`once()`, `as()`, `until()`, `fresh()`, `shareOnce()`) | ✓ | ✓ |
| Scroll props for `<InfiniteScroll>` | ✓ (Laravel paginators) | ✓ (TYPO3 paginators, arrays) |
| Validation errors, error bags, `withAllErrors` | ✓ (Laravel session) | ✓ (TYPO3 frontend session) |
| Flash data (page `flash`) | ✓ | ✓ |
| History encryption and clearing | ✓ (also as middleware) | ✓ (flags on `InertiaService`) |
| Testing assertions | ✓ `assertInertia()` | ✓ `InteractsWithInertia`, `AssertableInertia` |
| Reloads and deferred props in testing assertions (`reloadOnly()`, `loadDeferredProps()`) | ✓ | – build them with `inertiaReloadRequest()` |
| Server-side rendering | ✓ | – planned |
| Live props (broadcasting) | ✓ | – |
| Rescued deferred props (`rescue`) | ✓ | – |
| `ProvidesInertiaProperties`, `Arrayable` and `Responsable` props | ✓ | – (`JsonSerializable` works) |
| BigInt preservation, `preserveFragment` across redirects | ✓ | – |
| Precognition | ✓ | – |
| Dev tools | ✓ | – |
| Component existence check in tests | ✓ | – |

## Roadmap

- Server-side rendering, in its own pull request.
- Rescued deferred props and BigInt preservation, if there is demand.
- Publishing to Packagist and the TYPO3 Extension Repository.

## Contributing

See the [Contributing Guidelines](CONTRIBUTING.md). Run `ddev verify` before opening a pull request.

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0). See the [LICENSE](LICENSE) file for details.
