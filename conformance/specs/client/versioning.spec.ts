import { expect, test, type Page } from "@playwright/test";

import { documentIsMarked, markDocument, rendered, visit } from "../support";

/** Makes the client's next Inertia visits look like they come from an older build. */
async function sendStaleVersion(page: Page): Promise<void> {
  await page.route("**/*", async (route) => {
    const headers = route.request().headers();
    if (headers["x-inertia"] === "true") {
      await route.continue({ headers: { ...headers, "x-inertia-version": "stale" } });
    } else {
      await route.continue();
    }
  });
}

test("C11 the client does a full page load when the server answers a stale version with a 409", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  await markDocument(page);
  await sendStaleVersion(page);
  const conflict = page.waitForResponse((response) => response.status() === 409);

  await page.getByTestId("link-other").click();

  expect((await conflict).headers()["x-inertia-location"]).toMatch(/\/dump\/other$/);
  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  expect(await documentIsMarked(page)).toBe(false);
});

test("C13 flash data survives the version reload", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  // Flash through the browser's session, then visit with a stale version.
  const flashed = await page.request.post("/_c/flash", { maxRedirects: 0 });
  expect(flashed.status()).toBe(303);
  await sendStaleVersion(page);

  await page.getByTestId("link-other").click();

  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  expect((await rendered(page)).flash).toEqual({ message: "Flashed once" });
});
