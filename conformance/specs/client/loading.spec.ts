import { expect, test } from "@playwright/test";

import { pageFromHtml, recordInertiaRequests, visit } from "../support";

test("C29 infinite scroll appends the next page", async ({ page }) => {
  await visit(page, "/scroll", "Scroll");
  await expect(page.getByTestId("entry")).toHaveCount(10);
  const requests = recordInertiaRequests(page);

  await page.getByTestId("entry").last().scrollIntoViewIfNeeded();

  await expect(page.getByTestId("entry")).toHaveCount(20);
  await expect(page.getByTestId("entry").nth(19)).toHaveText("Entry 20");
  expect(requests[0].headers()["x-inertia-infinite-scroll-merge-intent"]).toBe("append");
});

test("C29 infinite scroll prepends the previous page", async ({ page }) => {
  // The page starts at the top, so the client loads the previous page at once.
  const requests = recordInertiaRequests(page);
  const response = await page.goto("/scroll?page=3");
  const initial = pageFromHtml((await response?.text()) ?? "");
  expect((initial.props.items as { data: { name: string }[] }).data[0].name).toBe("Entry 21");
  expect(initial.scrollProps).toMatchObject({ items: { currentPage: 3, previousPage: 2, nextPage: null } });

  await expect(page.getByTestId("entry").first()).toHaveText("Entry 11");
  expect(requests.some((request) => request.headers()["x-inertia-infinite-scroll-merge-intent"] === "prepend")).toBe(
    true,
  );
});

test("C30 usePoll reloads the polled prop at its interval", async ({ page }) => {
  const requests = recordInertiaRequests(page);
  await visit(page, "/poll", "Poll");
  const first = await page.getByTestId("tick").textContent();

  await expect.poll(() => requests.length, { timeout: 5000 }).toBeGreaterThanOrEqual(2);

  expect(requests.every((request) => request.headers()["x-inertia-partial-data"] === "tick")).toBe(true);
  await expect(page.getByTestId("tick")).not.toHaveText(first ?? "");
});

test("C31 a prefetching link sends Purpose: prefetch and the click uses the cached response", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  const requests = recordInertiaRequests(page);

  await page.getByTestId("link-prefetch").hover();
  await expect.poll(() => requests.length).toBe(1);
  expect(requests[0].headers()["purpose"]).toBe("prefetch");
  await requests[0].response();

  await page.getByTestId("link-prefetch").click();

  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  expect(requests).toHaveLength(1);
});
