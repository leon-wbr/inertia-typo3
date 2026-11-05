# Provable client compatibility and TYPO3-native features

Plan of 5 November 2026. It has two goals:

1. **Prove compatibility with the official Inertia clients.** A conformance harness runs `@inertiajs/react`, `@inertiajs/vue3` and `@inertiajs/svelte` (3.11.0, the current Inertia 3 releases) against a real TYPO3 14.3 with this adapter, in CI and locally.
2. **Make the adapter TYPO3-native.** Every relevant TYPO3 frontend feature reaches the client through the adapter, with a defined Inertia convention.

The protocol-level gap analysis already exists: [TODO.md](../../TODO.md) audits the adapter against inertia-laravel 3.7.0 and @inertiajs/core 3.11.0 (145 rows). This plan doesn't repeat it. It adds what TODO.md lacks: a list of observable behaviours that a test against a real client can check (the conformance list), the TYPO3 feature inventory, and the order of work.

State of the 3 Nov branches: all of them are merged or closed already.
- `feat/typo3-14-inertia-3` is #9.
- `feat/example-sitepackage` is #10.
- `feat/inertia-3-features` is #11.
- `feat/inertia-parity-audit` is #12.
- In `leon-wbr/inertia-sitepackage`, `feat/typo3-14-inertia-3` was #2. It was closed when that repository was archived and folded into `example/packages/sitepackage`.

## 1. The conformance harness

### Design

The harness is a TYPO3 project of its own in `conformance/`, next to `example/`, and it runs in the same DDEV web container.

- **TYPO3.** A minimal Composer project: core, frontend, extbase and fluid, with the adapter from the repository root as a path repository. Its database is `conformance`, next to the example's `db`. Apache serves it on a second port (`http://localhost:8090` in the container, `.ddev/apache/conformance.conf`), so the example site is untouched and the two never share state.
- **Cases are TYPO3 pages.** A page tree fixture has one page per case, and requests run through the real frontend stack:
  - site and language resolution;
  - the frontend user session, which holds flash data and errors;
  - the adapter's `InertiaMiddleware`;
  - page resolution and TypoScript;
  - the `MainController` page plugin, with `PropagateResponseException` for JSON.

  The conformance extension (`conformance/packages/conformance`, `EXT:inertia_conformance`) holds:
  - a `BeforeInertiaPageRenderedEvent` listener that sets each case page's component and props, using the adapter's prop types;
  - a middleware for non-GET and redirect endpoints, placed after `prepare-tsfe-rendering`, like the example's contact form;
  - an `InertiaMiddleware` subclass for shared props.
- **Official clients, unchanged.** Each client is a small Vite app built from npm: `clients/react`, `clients/vue` and `clients/svelte`. They use the same page names and the same DOM contract (`data-testid`s). A page shows its props and the page-object fields as JSON. It also uses the client APIs under test directly: `<Link>`, `router.*`, `useForm`/`<Form>`, `<Deferred>`, `<WhenVisible>`, `<InfiniteScroll>`, `usePoll`, `useRemember` and prefetching.
- **Choosing the client.** The root view (`App.html`) loads one client's bundle, picked by the cookie `inertia-conformance-client`. A ViewHelper reads it. The version is the hash of the React manifest, and all clients share it.
- **One spec suite, one Playwright project per client.** Each project sets the cookie, so every case runs once per client, and the result is the case × client matrix. The specs sit at two levels:
  - **HTTP**: raw requests with Inertia headers, for server-only rules. Examples: `Vary`, the 409 bodies, 303 after PUT, `Purpose: prefetch`. These run once, in the `http` project.
  - **Client**: a real browser drives the official client and asserts what the user and the client state see, for example `history.state`, the DOM and the request headers the client sent.
- **Known gaps are marked as expected failures.** A case for a feature the adapter lacks or gets wrong is a `test.fail()` that names its TODO.md row. CI stays green. Fixing the feature turns the test into an "unexpected pass", which fails CI until the marker is removed. The list can't rot.
- **The matrix is generated.** A Playwright reporter (`conformance/reporter/matrix.ts`) writes `conformance/matrix.md` and `matrix.json`:
  - `pass`: passed;
  - `fail`: failed unexpectedly;
  - `missing`: an expected failure;
  - `n/a`: skipped for that client.

  CI uploads both as an artifact and writes the table to the job summary.
- **Commands.**
  - `ddev conformance [react|vue|svelte|http]` sets up the project, builds the clients and runs the suite.
  - `Build/Scripts/conformance.sh` is the same command, in the container.
  - The CI workflow `.github/workflows/conformance.yml` uses the DDEV GitHub action, like `ci.yml`.

Why not run Inertia's own test suite? `inertia/tests/app/server.js` is a 4,600-line Express stand-in for a server. Porting all its routes would mean testing the stand-in, not TYPO3. The harness instead ports the cases that depend on server behaviour, from two sources: inertia-laravel's PHPUnit suite (server rules) and the client's Playwright specs (behaviour against a real client). Each case cites its source.

### Conformance list

IDs are stable; the matrix uses them. Sources:
- **P**: the [protocol page](https://inertiajs.com/docs/v3/core-concepts/the-protocol);
- **IL**: an `inertia-laravel` v3.7.0 test (`tests/<File>::<method>`);
- **IC**: an `inertia` v3.11.0 client spec (`tests/<file>.spec.ts`).

The "Today" column gives the expected status before any fix, from TODO.md:
- `ok`: expected to pass;
- `gap`: a known gap, so the test is `test.fail()`;
- `later`: no case yet; it is added with its feature.

| ID | Case | Level | Source | Today |
| --- | --- | --- | --- | --- |
| **First visit** | | | | |
| C01 | The HTML response has the page object in `<script data-page="app" type="application/json">` and the client mounts it | client | P "HTML responses"; IL `DirectiveTest` | ok |
| C02 | `</script>` and `<` in props can't break out of the page script | client | IL `DirectiveTest`; P | ok |
| C03 | `url` keeps the query string and the trailing slash | http+client | IL `ResponseTest::test_trailing_slashes_in_a_url_are_preserved` and siblings | ok |
| C04 | Empty `props` is `{}` and `errors` is `{}` | http | P "the page object"; IL `MiddlewareTest::test_validation_errors_can_be_empty` | ok |
| **Inertia visits** | | | | |
| C05 | A `<Link>` click sends `X-Inertia` and `X-Inertia-Version`, gets JSON with `X-Inertia: true`, and the URL and component change without a full load | client | P "request/response headers"; IC `links.spec.ts` | ok |
| C06 | Responses vary on `X-Inertia`, including existing `Vary` values | http | IL `MiddlewareTest::test_the_vary_header_is_set`, `…_preserved`, `…_not_duplicated` | ok |
| C07 | Back and forward restore pages from history without a request | client | IC `history.spec.ts` | ok |
| C08 | `preserveScroll` and `preserveState` keep the scroll position and local state; scroll regions are restored | client | IC `remember.spec.ts`, `scroll-*.spec.ts` | ok |
| C09 | `useRemember` state survives back navigation | client | IC `remember.spec.ts` | ok |
| **Asset versioning** | | | | |
| C10 | A stale `X-Inertia-Version` on GET gets a 409 with `X-Inertia-Location` (the same URL) and the current `X-Inertia-Version` | http | IL `MiddlewareTest::test_it_will_instruct_inertia_to_reload_on_a_version_mismatch` | ok |
| C11 | The client reacts to that 409 with a full page load of the target | client | P "asset versioning"; IC `initial-visit.spec.ts` | ok |
| C12 | Non-GET requests skip the version check | http | P | ok |
| C13 | Flash data and errors survive the version reload | client | IL `MiddlewareTest` reflash; TODO.md group 3 | ok |
| **Partial reloads** | | | | |
| C14 | `router.reload({only})` sends `X-Inertia-Partial-Component` and `-Data`; only those props come back, other props keep their values | client | P "partial reloads"; IL `ResponseTest::test_xhr_partial_response` | ok |
| C15 | `except` leaves props out | client | IL `ResponseTest::test_exclude_props_from_partial_response` | ok |
| C16 | Nested dot paths in `only` and `except` | http | IL `ResponseTest::test_nested_partial_props` | ok |
| C17 | A partial request for another component returns all props | http | P | ok |
| C18 | `always` props and `errors` are in every partial reload | http | IL `ResponseTest::test_always_props_are_included_on_partial_reload` | ok |
| **Deferred and optional props** | | | | |
| C19 | `<Deferred>` shows the fallback, then the client fetches `deferredProps` groups in parallel requests | client | P; IC `deferred-props.spec.ts` "can load deferred props", "load deferred props in multiple groups" | ok |
| C20 | Deferred props keep the query string of the original URL | client | IC `deferred-props.spec.ts` | ok |
| C21 | A rescued deferred prop that throws arrives as `rescuedProps`, and `<Deferred>` shows its error UI | client | P "rescued deferred props"; IC `deferred-props.spec.ts` "can render rescued…" | gap |
| C22 | `optional` props are absent until requested; `<WhenVisible>` loads them when scrolled into view | client | IL `ResponseTest::test_optional_props_are_not_included_by_default`; IC `when-visible.spec.ts` | ok |
| **Merging** | | | | |
| C23 | `merge` props append on reload (`mergeProps`) | client | P "merging props"; IC `merge-props.spec.ts` | ok |
| C24 | `prepend`, `deepMerge` and `matchOn` (`prependProps`, `deepMergeProps`, `matchPropsOn`) | client | IC `deep-merge-props.spec.ts`, `match-props-on-key.spec.ts`; IL `ResponseTest::test_server_response_with_*merge*` | ok |
| C25 | `reset` sends `X-Inertia-Reset`, and the server leaves the merge metadata out | client | IL `ResponseTest::test_exclude_merge_props_when_passed_in_reset_header` | ok |
| **Once props** | | | | |
| C26 | Once props are sent with `onceProps`; the client sends `X-Inertia-Except-Once-Props` later, and the server leaves them out while the client keeps them | client | P "once props"; IC `once-props.spec.ts`; IL `ResponseTest::test_once_props_*` | ok |
| C27 | `fresh()` and expiry (`until`) send them again | http+client | IL `ResponseTest::test_fresh_props_*`, `…custom_key_and_ttl…` | ok |
| **Instant visits and shared props** | | | | |
| C28 | `sharedProps` lists the shared keys, and an instant visit shows the target component at once with them | client | IC `instant-visits.spec.ts` | ok |
| **Infinite scroll** | | | | |
| C29 | `<InfiniteScroll>` loads the next page with `scrollProps`; scrolling up prepends (`X-Inertia-Infinite-Scroll-Merge-Intent`) | client | P "infinite scroll"; IC `infinite-scroll.spec.ts` | ok |
| **Polling and prefetch** | | | | |
| C30 | `usePoll` repeats partial reloads at its interval | client | IC `poll.spec.ts` | ok |
| C31 | `<Link prefetch>` sends `Purpose: prefetch`, and the click uses the cached response without a second request | client | IC `prefetch.spec.ts` "can prefetch using link props" | ok |
| C32 | A prefetch whose server answer is a redirect to a URL with a fragment gets the redirect unchanged, not a 409 | http | IL `MiddlewareTest::test_redirect_with_hash_fragment_is_not_intercepted_for_prefetch_requests` | gap |
| C33 | A prefetch doesn't take pending flash data or errors from the session | http | TODO.md group 10 | gap |
| **Forms, validation and redirects** | | | | |
| C34 | A `useForm`/`<Form>` POST with errors redirects back; `errors` shows the first message per field, then clears | client | P; IC `form-helper.spec.ts`; IL `MiddlewareTest::test_validation_errors_*` | ok |
| C35 | An error bag (`errorBag`/`X-Inertia-Error-Bag`) scopes the errors | client | IL `MiddlewareTest::test_validation_errors_are_scoped_to_error_bag_header` | ok |
| C36 | 302 after PUT, PATCH and DELETE becomes 303, and the client follows with GET | http+client | P "303"; IL `MiddlewareTest` | ok |
| C37 | 302 after QUERY becomes 303 | http | IL `MiddlewareTest::test_redirects_from_query_requests_are_converted_to_…` | gap |
| C38 | An empty 200 after a POST redirects back (303 to `Referer`) | http | IL `MiddlewareTest::test_no_response_value_by_default_means_automatically_redirecting_back…` | ok |
| C39 | Responses the middleware replaces (the empty-response 303 and the fragment 409) keep `Vary: X-Inertia` | http | IL `MiddlewareTest::test_the_vary_header_is_set` | gap |
| C40 | A redirect to a URL with a fragment gets a 409 with `X-Inertia-Redirect`, and the client lands on the fragment | client | P "redirects"; IL `MiddlewareTest::test_redirect_with_hash_fragment_returns_…` | ok |
| C41 | `location()` to an external URL gets a 409 with `X-Inertia-Location`, and the client does `window.location` | client | P "external redirects"; IL `ResponseFactoryTest` | ok |
| C42 | Flash data arrives once as the page's `flash`, not as a prop | client | P "flash data"; IC `flash.spec.ts` | ok |
| C43 | Multipart PUT with a file, or POST with `_method=put`, reaches the handler with fields and the file | http+client | P "file uploads"; TODO.md bug 5 | gap |
| C44 | A 404 or 500 on an Inertia visit renders a page component, not the client's error modal | client | P "error handling"; TODO.md group 3 | gap |
| **History** | | | | |
| C45 | `encryptHistory` encrypts `history.state` | client | IC `history.spec.ts` "it can encrypt history"; IL `HistoryTest` | ok |
| C46 | `clearHistory` rotates the key, and older entries are fetched again | client | IC `history.spec.ts` "history can be cleared via props" | ok |
| C47 | `clearHistory()` before a redirect, as in a logout, reaches the next page | client | IL `HistoryTest::test_the_history_can_be_cleared_when_redirecting` | gap |
| C48 | Global history encryption (a site setting) | http | IL `HistoryTest` config | gap |
| C49 | `preserveFragment()` keeps the fragment across a redirect | client | IC `preserve-fragment.spec.ts` | gap |
| **SSR** | | | | |
| C50 | With SSR on, the first response has the server-rendered body and head; the client hydrates it without a mismatch | client | P "server-side rendering"; IC `ssr.spec.ts` | later (SSR PR) |
| C51 | When the SSR server is down, the page falls back to client-side rendering | http | IL `HttpGatewayTest` | later (SSR PR) |
| **Later** | | | | |
| C52 | Big integers (`preserveBigIntegers`, `$bigint`) | client | IC `bigint.spec.ts` | later |
| C53 | Precognition (`Precognition` → 204/422) | http | IC `precognition.spec.ts` | later |
| C54 | Live props (`liveProps`, `X-Socket-Id`) | client | IC `live.spec.ts` | later |

Svelte and Vue run every client case except where a client lacks the API. Such cases are skipped with a reason, which the matrix shows as `n/a`.

## 2. TYPO3-native inventory

What a TYPO3 v13/v14 site expects to reach the client, and how. Status:
- `works`: works and is tested;
- `partial`: some of it works;
- `missing`: not there;
- `unverified`: should work through TYPO3, but nothing tests it.

The proposed convention says how the adapter should expose each feature. Most go through the shared props of `InertiaMiddleware`, as closures, so partial reloads skip them. Page-specific data goes through `BeforeInertiaPageRenderedEvent`. The EXT:headless comparison of 3 Nov (`typo3-headless-parity`) is the evidence for several rows.

| Feature | Status | Today | Proposed convention |
| --- | --- | --- | --- |
| **Page tree and menus** | partial | `NavigationService` builds the main menu from the page tree; `nav_hide` and hidden pages are respected (`NavigationServiceTest`, e2e `first-load`). Breadcrumbs, sub menus and a language menu exist only in the example; translation availability is ignored. | An opt-in shared prop `navigation` with `main`, `breadcrumb` and `languages`, built on core's `MenuProcessor`/`LanguageMenuProcessor` semantics and configured by site settings (`inertia.navigation.*`). |
| **Content elements, colPos, backend layouts** | partial | Only `colPos=0` (`MainController.php:48-52`). Content goes out as typed `data` plus a TypoScript HTML fallback per element (`InertiaContentElementRenderer`). Links in `data` stay `t3://`, RTE is unparsed and FlexForms serialise to `null`. | `content` as `{colPos: [elements]}` for every column of the page's backend layout, with `backendLayout` (the identifier and its rows) as a prop. Links resolved to URLs, RTE parsed through `lib.parseFunc_RTE`, FlexForms as arrays. |
| **Site configuration and languages** | partial | Site language routing works (e2e `languages.spec.ts`, strict fallback). The example shares site title, language and languages; the adapter shares none. hreflang is correct only on full loads (`PAGE` head). | A shared prop `site` (`title`, `base`, `language` {id, locale, code, direction}, `languages` with a translated link or `null` when unavailable under the fallback type). `seo.hreflang` comes from core's `HrefLangGenerator`. |
| **Routing, slugs, route enhancers** | unverified | TYPO3 resolves every URL, so slugs, `PageTypeSuffix`, Extbase enhancers and cHash work as on any page. The page `url` comes from the request. RTE links in the HTML fallback become Inertia links in the example. | Nothing to add. Conformance cases for an Extbase route enhancer with cHash, and for a `PageTypeSuffix` URL. |
| **TypoScript and page TSconfig values** | missing | No access from the client except through a custom `share()`. | Site settings (v13+) are the native source. An allow-list setting `inertia.share.settings` sends chosen site settings as `settings`. TypoScript constants and TSconfig are backend or rendering configuration, so they are documented as not sent by default. |
| **FE users and groups (auth, access)** | partial | The frontend user session holds flash data and errors, for anonymous visitors too. No `auth` prop. An access-restricted page on an Inertia visit gets TYPO3's 403/login HTML. | A shared prop `auth` (`user`: uid, username, name, email, `groups`: uids/titles; `null` when logged out; the fields are a site setting). An access-denied page on an Inertia visit becomes a 409 `X-Inertia-Location` to the login page (felogin `redirect_url`), or renders the error component (see errors). felogin's login and logout set `clearHistory`. |
| **Flash messages** | partial | `InertiaService::flash()` sends the page's `flash`. Core and Extbase `FlashMessageQueue` messages (`addFlashMessage()`) don't reach the client. | A bridge that drains `FlashMessageService` queues into `flash.messages` (`[{title, message, severity}]`) when a page renders. |
| **Forms (EXT:form) and validation errors** | missing | Validation errors work through `withErrors()` and redirect-back (e2e `form.spec.ts`). EXT:form renders as HTML in the content fallback and posts as a full page load. Extbase validation results are not mapped to `errors`. | (a) Extbase: an `ActionController` trait whose `errorAction()` maps `$this->arguments->validate()` results to `withErrors()` and redirects back (303). (b) EXT:form: the form definition as `data` for a typed `form_formframework` element, a submit endpoint that runs the finishers, and validation errors mapped to `errors` (an error bag per form identifier). |
| **Extbase plugins and controllers** | partial | `MainController` is Extbase and propagates JSON with `PropagateResponseException`. Other plugins render HTML in the content fallback. No public API turns an Extbase action into an Inertia response. | An `InertiaActionController` (or trait) with `inertia(component, props)`, which handles the propagate-or-return choice, so a plugin can own the page component (with its prop types) for its route enhancer URLs. |
| **Caching** | partial | The `PAGE` shell stays in the page cache, while the page object is built per request (USER_INT plugin). JSON never enters the page cache (`InertiaFrontendTest::pagesStayInThePageCache`). The asset version is the manifest hash. Nothing is cached per page and no cache tags are set. | Cache the resolved, cacheable part of the page object (content, navigation) in a cache with the page's tags (`pageId_<uid>`, plus record tags from content), so a backend save flushes it. Request-dependent props (`auth`, flash, errors) are resolved per request. The asset version stays the manifest hash; a site setting can pin it. |
| **SEO (meta tags, canonical)** | partial | Full loads get the `PAGE` head (EXT:seo meta, canonical, hreflang). Inertia visits keep the old head except for the title the client sets. | A shared prop `seo` (`title` from the page title provider, `meta` from the `MetaTagManagerRegistry`, `canonical` from EXT:seo's `CanonicalGenerator`, `hreflang`, `robots`), filled after page rendering. A small `<Head>` recipe per client. With SSR, it goes into the SSR head. |
| **Redirects (EXT:redirects)** | unverified | Redirects to the same host should be followed by XHR. A redirect to another host fails as a cross-origin XHR (CORS). | Middleware: on Inertia requests, a 3xx to another origin becomes a 409 `X-Inertia-Location`. Pin the adapter middleware before `typo3/cms-redirects/redirecthandler`. Conformance case with a `sys_redirect` row. |
| **Files and media (FAL, image processing)** | partial | File references are resolved (lazy collections fixed in #9). Images are sent as original URLs only; no processing and no crop variants. | `image`/`assets` as `{url, width, height, alternative, title, mimeType, variants: {default: {url, width, height}, …}}` from `ImageService`, with the crop variants of the field's TCA and a site setting for the processing instructions per variant. |
| **Frontend editing, preview, workspaces** | unverified | A backend user's preview of hidden pages and workspaces goes through TYPO3's normal page access. JSON isn't cached, so previews can't leak. Not tested. The admin panel needs HTML. | Conformance cases: a hidden page with a backend session, and a workspace preview link. A shared prop `backend` (edit links for the page and content uids) only for a logged-in backend user. Frontend editing (EXT:frontend_editing) is out of scope. |
| **Errors (404, 403, 500)** | missing | Site `errorHandling` answers with HTML. `PageContentErrorHandler` copies only `Content-Type`, so an Inertia visit gets the HTML and the client shows its error modal. | Error handler: on Inertia requests, render an error component (site setting `inertia.errorComponent`, props `status`, `message` and the shared props) with the right status code. Full loads keep TYPO3's error pages. |
| **CSP and nonces** | unverified | The page script is `type="application/json"` and not executed, so it is CSP-safe. The client's progress bar injects a `<style>`, which a strict `style-src` blocks unless it gets a nonce. | Expose TYPO3's nonce (`ConsumableNonce` from the `nonce` request attribute) to the root view and as `<meta name="csp-nonce">`, so the client's progress option can use it. Conformance case with `security.frontend.enforceContentSecurityPolicy`. |

## 3. Pull request sequence

Each PR is focused and does one thing. PRs are drafts until they are green and complete, and Leon reviews all of them. Each feature PR removes the `test.fail()` from its conformance cases, so the matrix records the progress.

1. **docs: this plan.** It also lists the state of the 3 Nov branches.
2. **test: conformance harness with the official React, Vue and Svelte clients.** The `conformance/` TYPO3 project, the extension and the case pages, the three clients, the specs (gaps as `test.fail()`), the matrix reporter, `ddev conformance` and `.github/workflows/conformance.yml`.
3. **fix: middleware protocol bugs.** QUERY → 303, `Vary` on replaced responses, prefetch fragment redirects passed through, prefetches leave flash data in the session. Closes C32, C33, C37 and C39 (TODO.md bugs 1–3).
4. **feat: session-backed clearHistory and preserveFragment.** Closes C47 and C49 (bug 4).
5. **feat: global history encryption.** A site setting and an `EncryptHistory` middleware. Closes C48.
6. **feat: rescued deferred props.** `defer(..., rescue: true)`, `rescuedProps` and logging. Closes C21.
7. **feat: multipart PUT/PATCH and `_method`.** Closes C43 (bug 5).
8. **feat: error pages through Inertia.** 404/403/500 as an error component. Closes C44 and the errors row.
9. **feat: SSR.** The HTTP gateway, head and body injection, fallback, Vite dev SSR and console commands. Adds C50–C51. With this and item 6, the adapter is "v3", not "v3 (partial)", for the community adapters listing.

Then the TYPO3-native mappings, in this order (impact first):

10. **feat: `seo` shared prop.** Meta, canonical, hreflang and robots on Inertia visits.
11. **feat: content by colPos and backend layout**, with resolved links, parsed RTE and FlexForm arrays.
12. **feat: processed images and crop variants.**
13. **feat: `site` and `navigation` shared props** in the adapter: languages with availability, breadcrumbs.
14. **feat: `auth` shared prop and access restrictions** (felogin, `clearHistory` on logout).
15. **feat: flash message queue bridge and Extbase validation errors.**
16. **feat: `InertiaActionController` for Extbase plugins.**
17. **feat: EXT:form integration.**
18. **feat: page object cache with cache tags.**
19. **fix: cross-origin redirects (EXT:redirects) as `X-Inertia-Location`.**
20. **feat: CSP nonce.**
21. **test: preview and workspaces cases.**

The parity check (`Build/Parity`, weekly) keeps reporting new Inertia releases. The conformance workflow runs on every PR and on Dependabot's `@inertiajs/*` bumps, so a new client release that breaks against TYPO3 shows up as a red PR.
