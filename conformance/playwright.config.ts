import { existsSync } from "node:fs";
import { resolve } from "node:path";

import { defineConfig, devices } from "@playwright/test";

/**
 * The conformance suite. `ddev conformance` runs it in the web container,
 * where the conformance site answers on http://localhost:8090.
 *
 * - http: server rules, checked with raw requests (specs/http);
 * - react, vue, svelte: the official clients in Chromium (specs/client).
 *   The cookie makes TYPO3's root view load that client's bundle. A client
 *   without a build is left out.
 */
const baseURL = process.env.CONFORMANCE_BASE_URL ?? "http://localhost:8090";
const host = new URL(baseURL).hostname;

const clients = ["react", "vue", "svelte"].filter((client) =>
  existsSync(resolve(import.meta.dirname, `public/_assets/conformance/${client}/.vite/manifest.json`)),
);

export default defineConfig({
  fullyParallel: true,
  forbidOnly: true,
  retries: 0,
  timeout: 15_000,
  reporter: [["list"], ["html", { open: "never" }], ["./reporter/matrix.ts"]],
  use: {
    baseURL,
    trace: "retain-on-failure",
  },
  projects: [
    { name: "http", testDir: "./specs/http" },
    ...clients.map((client) => ({
      name: client,
      testDir: "./specs/client",
      use: {
        ...devices["Desktop Chrome"],
        storageState: {
          cookies: [
            {
              name: "inertia-conformance-client",
              value: client,
              domain: host,
              path: "/",
              expires: -1,
              httpOnly: false,
              secure: false,
              sameSite: "Lax" as const,
            },
          ],
          origins: [],
        },
      },
    })),
  ],
});
