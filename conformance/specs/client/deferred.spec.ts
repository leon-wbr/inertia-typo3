import { expect, test } from "@playwright/test";

import { pageFromHtml, recordInertiaRequests } from "../support";

test("C19 deferred props load after the page, one request per group", async ({ page }) => {
  const requests = recordInertiaRequests(page);
  const response = await page.goto("/deferred");
  const initial = pageFromHtml((await response?.text()) ?? "");

  expect(initial.props).not.toHaveProperty("slow");
  expect(initial.deferredProps).toEqual({ default: ["slow", "query"], other: ["other"] });
  await expect(page.getByTestId("slow")).toHaveText("slow value");
  await expect(page.getByTestId("other")).toHaveText("other value");
  const groups = requests.map((request) => request.headers()["x-inertia-partial-data"]).sort();
  expect(groups).toEqual(["other", "slow,query"]);
});

test("C20 deferred props are requested with the query string of the page", async ({ page }) => {
  await page.goto("/deferred?filter=new");

  await expect(page.getByTestId("query")).toHaveText("filter=new");
});

test("C21 a rescued deferred prop that throws shows the rescue UI", async ({ page }) => {
  test.fail(true, "TODO.md: defer(..., rescue: true) and rescuedProps are missing");
  await page.goto("/deferred/rescue");

  await expect(page.getByTestId("other")).toHaveText("other value");
  await expect(page.getByTestId("rescue-slow")).toBeVisible({ timeout: 3000 });
});
