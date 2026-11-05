import { expect, test, type Page } from "@playwright/test";

import { recordInertiaRequests, renderedProps, visit } from "../support";

async function routerCall(page: Page, method: string, ...args: unknown[]): Promise<void> {
  await page.evaluate(
    ([name, rest]) => {
      const router = window.conformance.router as unknown as Record<string, (...a: unknown[]) => void>;
      router[name as string](...(rest as unknown[]));
    },
    [method, args] as const,
  );
}

test("C14 router.reload({only}) asks for some props and keeps the others", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  const before = await renderedProps(page);
  const requests = recordInertiaRequests(page);

  await routerCall(page, "reload", { only: ["requestedAt", "lazy"] });

  await expect.poll(async () => (await renderedProps(page)).requestedAt).not.toBe(before.requestedAt);
  const after = await renderedProps(page);
  expect(after.lazy).toBe("lazy value");
  expect(after.expensive).toBe("expensive value");
  expect(after.nested).toEqual(before.nested);
  expect(requests[0].headers()["x-inertia-partial-component"]).toBe("Dump");
  expect(requests[0].headers()["x-inertia-partial-data"]).toBe("requestedAt,lazy");
  const response = await (await requests[0].response())?.json();
  expect(Object.keys(response.props).sort()).toEqual(["always", "errors", "lazy", "requestedAt"]);
});

test("C15 router.reload({except}) leaves props out", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  const requests = recordInertiaRequests(page);

  await routerCall(page, "reload", { except: ["requestedAt"] });

  await expect.poll(() => requests.length).toBe(1);
  expect(requests[0].headers()["x-inertia-partial-except"]).toBe("requestedAt");
  const response = await (await requests[0].response())?.json();
  expect(response.props).not.toHaveProperty("requestedAt");
  expect(response.props).toHaveProperty("nested");
});

test("C22 optional props are left out until asked for; WhenVisible loads them in view", async ({ page }) => {
  await visit(page, "/when-visible", "WhenVisible");
  await expect(page.getByTestId("fallback-lazy")).toBeAttached();
  const requests = recordInertiaRequests(page);

  await page.getByTestId("fallback-lazy").scrollIntoViewIfNeeded();

  await expect(page.getByTestId("lazy")).toHaveText("visible value");
  expect(requests[0].headers()["x-inertia-partial-data"]).toBe("lazy");
});

test("C23 merge props append what a reload brings", async ({ page }) => {
  await visit(page, "/merge", "Dump");
  expect((await renderedProps(page)).items).toEqual([1, 2, 3].map((id) => ({ id, name: `Item ${id}` })));

  await routerCall(page, "reload", { data: { page: 2 }, only: ["items", "feed", "settings", "page"] });

  await expect.poll(async () => (await renderedProps(page)).page).toBe(2);
  const props = await renderedProps(page);
  expect((props.items as { id: number }[]).map((item) => item.id)).toEqual([1, 2, 3, 4, 5, 6]);
});

test("C24 prepend, deepMerge and matchOn", async ({ page }) => {
  await visit(page, "/merge", "Dump");

  await routerCall(page, "reload", { data: { page: 2 }, only: ["items", "feed", "settings", "page"] });
  await expect.poll(async () => (await renderedProps(page)).page).toBe(2);
  await routerCall(page, "reload", { data: { page: 2 }, only: ["items", "feed", "settings", "page"] });
  await expect.poll(async () => ((await renderedProps(page)).feed as string[]).length).toBe(3);

  const props = await renderedProps(page);
  expect(props.feed).toEqual(["entry 2", "entry 2", "entry 1"]);
  expect(props.settings).toEqual({ pages: { 1: true, 2: true }, last: 2 });
  // matchOn id: page 2 again replaces items 4-6 instead of adding them twice.
  expect((props.items as { id: number }[]).map((item) => item.id)).toEqual([1, 2, 3, 4, 5, 6]);
});

test("C25 reset replaces a merge prop instead of merging", async ({ page }) => {
  await visit(page, "/merge", "Dump");
  const requests = recordInertiaRequests(page);

  await routerCall(page, "reload", { data: { page: 2 }, only: ["items", "page"], reset: ["items"] });

  await expect.poll(async () => (await renderedProps(page)).page).toBe(2);
  expect(requests[0].headers()["x-inertia-reset"]).toBe("items");
  expect(((await renderedProps(page)).items as { id: number }[]).map((item) => item.id)).toEqual([4, 5, 6]);
  const response = await (await requests[0].response())?.json();
  expect(response.mergeProps ?? []).not.toContain("items");
});

test("C26 once props are kept by the client and skipped by the server", async ({ page }) => {
  await visit(page, "/once", "Dump");
  const first = await renderedProps(page);
  const requests = recordInertiaRequests(page);

  await page.getByTestId("link-once-other").click();

  await expect(page.getByTestId("url")).toHaveText("/once/other");
  const header = requests[0].headers()["x-inertia-except-once-props"] ?? "";
  expect(header.split(",")).toContain("plans");
  const response = await (await requests[0].response())?.json();
  expect(response.props).not.toHaveProperty("plans");
  expect(response.onceProps).toHaveProperty("plans");
  expect((await renderedProps(page)).plans).toBe(first.plans);
});

test("C27 fresh and expired once props are sent again", async ({ page }) => {
  await visit(page, "/once", "Dump");
  const first = await renderedProps(page);

  await page.getByTestId("link-once-other").click();

  await expect(page.getByTestId("url")).toHaveText("/once/other");
  const second = await renderedProps(page);
  expect(second.fresh).not.toBe(first.fresh);
  expect(second.short).not.toBe(first.short);
});
