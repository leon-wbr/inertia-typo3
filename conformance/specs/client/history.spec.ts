import { expect, test } from "@playwright/test";

import { recordInertiaRequests, visit } from "../support";

test("C45 encryptHistory keeps the page out of history.state in plain text", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  expect(await page.evaluate(() => JSON.stringify(window.history.state))).toContain('"component":"Dump"');

  await page.getByTestId("link-encrypted").click();
  await expect(page.getByTestId("url")).toHaveText("/history/encrypted");

  const state = await page.evaluate(() => JSON.stringify(window.history.state));
  expect(state).not.toContain("encrypted value");
});

test("C46 clearHistory makes the client fetch older encrypted pages again", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  await page.getByTestId("link-encrypted").click();
  await expect(page.getByTestId("url")).toHaveText("/history/encrypted");
  await page.getByTestId("link-clear").click();
  await expect(page.getByTestId("url")).toHaveText("/history/clear");
  const requests = recordInertiaRequests(page);

  await page.goBack();

  await expect(page.getByTestId("url")).toHaveText("/history/encrypted");
  expect(requests.map((request) => new URL(request.url()).pathname)).toContain("/history/encrypted");
});

test("C47 clearHistory before a redirect (a logout) reaches the next page", async ({ page }) => {
  test.fail(true, "TODO.md bug 4: clearHistory() is not kept across the redirect");
  await visit(page, "/dump", "Dump");
  await page.getByTestId("link-encrypted").click();
  await expect(page.getByTestId("url")).toHaveText("/history/encrypted");
  await page.getByTestId("link-logout").click();
  await expect(page.getByTestId("url")).toHaveText("/dump");
  const requests = recordInertiaRequests(page);

  await page.goBack();

  await expect(page.getByTestId("url")).toHaveText("/history/encrypted");
  expect(requests.map((request) => new URL(request.url()).pathname)).toContain("/history/encrypted");
});
