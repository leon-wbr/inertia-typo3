# Conformance harness

This harness runs the official Inertia clients (`@inertiajs/react`, `@inertiajs/vue3` and `@inertiajs/svelte`) against a real TYPO3 14.3 with this adapter. It checks every case of the conformance list in [docs/plans/2026-11-05-compat-and-native.md](../docs/plans/2026-11-05-compat-and-native.md).

```sh
ddev conformance                    # setup, build the clients, run every project
ddev conformance react http         # only these Playwright projects
ddev conformance --no-setup react   # reuse the database and the builds
```

The result is `conformance/matrix.md` (and `matrix.json`): one row per case, one column per project. CI runs the suite on every push (`.github/workflows/conformance.yml`), writes the matrix to the job summary and uploads it with the Playwright report.

## How it works

- **TYPO3.** A Composer project of its own, with the adapter as a path repository and its own database `conformance` in DDEV's MariaDB. Apache serves it on port 8090 in the web container (`.ddev/apache/conformance.conf`), next to the example site.
- **Cases are TYPO3 pages** ([fixtures/pages.sql](fixtures/pages.sql)), rendered by the adapter's page plugin.
  - [`CasePages`](packages/conformance/Classes/EventListener/CasePages.php) picks each page's component and props, with the adapter's prop types.
  - The endpoints under `/_c/` ([`CaseEndpoints`](packages/conformance/Classes/Middleware/CaseEndpoints.php)) are forms, redirects and control responses. They run inside the adapter's middleware, as any TYPO3 handler would.
- **Clients.** Each one lives in `clients/<client>/`, uses the same page names and the same DOM contract (`data-testid`, see [specs/support.ts](specs/support.ts)), and exposes its router as `window.conformance.router`. The cookie `inertia-conformance-client` makes the root view load that client's build, and each Playwright project sets it.
- **Projects.**
  - `http` checks server rules with raw requests (`specs/http`).
  - Each client project runs `specs/client` in Chromium.
- **Known gaps.** They are `test.fail()` with the TODO.md reference. The matrix shows them as `missing`. When a fix makes one pass, the run fails until the marker is removed.

## Adding a case

1. Add the page to `fixtures/pages.sql`, and its props to `CasePages`, or an endpoint to `CaseEndpoints`.
2. If the case needs a client API, add a page component to every client.
3. Write the spec with the case ID first in the title (`C23 …`). New IDs also go into `reporter/cases.ts` and the plan.
