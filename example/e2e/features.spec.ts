import { expect, test } from "@playwright/test";

import { initialPage } from "./helpers";

test("deferred props load right after the page", async ({ page }) => {
  const deferred = page.waitForResponse(
    async (response) =>
      response.url().endsWith("/features") &&
      (await response.request().headerValue("X-Inertia-Partial-Data")) === "stats",
  );
  await page.goto("/features");

  const data = await initialPage(page);
  expect(data.props).not.toHaveProperty("stats");
  expect(data.deferredProps).toEqual({ default: ["stats"] });

  const response = await deferred;
  expect(Object.keys((await response.json()).props).sort()).toEqual(["errors", "stats"]);
  await expect(page.getByTestId("stats")).toContainText("1 content elements");
});

test("optional props are only sent when asked for", async ({ page }) => {
  await page.goto("/features");
  expect((await initialPage(page)).props).not.toHaveProperty("details");

  const reload = page.waitForResponse(
    async (response) =>
      (await response.request().headerValue("X-Inertia-Partial-Data")) === "details",
  );
  await page.getByRole("button", { name: "Load details" }).click();
  const response = await reload;

  expect((await response.json()).props).toEqual({
    errors: {},
    details: { requestedWith: "details" },
  });
  await expect(page.getByTestId("details")).toHaveText("Loaded with: details");
});

test("merge props append to what the client has", async ({ page }) => {
  await page.goto("/features");
  const data = await initialPage(page);
  expect(data.mergeProps).toEqual(["items"]);
  expect(data.matchPropsOn).toEqual(["items.id"]);

  const items = page.getByTestId("items").getByRole("listitem");
  await expect(items).toHaveText(["Item 1", "Item 2", "Item 3"]);

  await page.getByRole("button", { name: "Load more" }).click();
  await expect(items).toHaveText(["Item 1", "Item 2", "Item 3", "Item 4", "Item 5", "Item 6"]);
  await expect(page).toHaveURL("/features?page=2");

  await page.getByRole("button", { name: "Load more" }).click();
  await expect(items).toHaveCount(9);
  await expect(page.getByRole("button", { name: "Load more" })).toHaveCount(0);
});
