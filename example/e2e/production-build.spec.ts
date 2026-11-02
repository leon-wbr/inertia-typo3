import { readFileSync } from "node:fs";

import { expect, test } from "@playwright/test";

import { initialPage } from "./helpers";

const manifestPath = new URL("../public/_assets/vite/.vite/manifest.json", import.meta.url);

test("production assets come from the Vite manifest", async ({ page, request }) => {
  // vite build wrote the manifest; TYPO3's .htaccess keeps it private.
  expect((await request.get("/_assets/vite/.vite/manifest.json")).status()).toBe(403);
  const manifest = JSON.parse(readFileSync(manifestPath, "utf8")) as Record<string, unknown>;
  const entries = Object.values(manifest) as { file: string; isEntry?: boolean }[];
  const entry = entries.find((item) => item.isEntry);
  expect(entry).toBeDefined();

  await page.goto("/");
  await expect(
    page.locator(`script[type="module"][src="/_assets/vite/${entry?.file}"]`),
  ).toHaveCount(1);
  await expect(page.locator('script[src*="@vite/client"]')).toHaveCount(0);

  // The asset version is the manifest hash, so it is set in production.
  expect((await initialPage(page)).version).toMatch(/^[0-9a-f]{32}$/);

  // React rendered the page from the built bundle.
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Home");
});
