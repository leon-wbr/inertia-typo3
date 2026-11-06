import { expect, test } from "@playwright/test";

import { documentIsMarked, markDocument, recordInertiaRequests, rendered, visit } from "../support";

test("C01 the client mounts the page object TYPO3 rendered into the HTML", async ({ page }) => {
  const response = await page.goto("/dump?x=1");
  const html = (await response?.text()) ?? "";

  expect(html).toContain('<script data-page="app" type="application/json">');
  await expect(page.getByTestId("component")).toHaveText("Dump");
  const { url, props } = await rendered(page);
  expect(url).toBe("/dump?x=1");
  expect(props).toMatchObject({ title: "Dump", app: { name: "conformance" }, errors: {} });
});

test("C02 a prop with </script> can't break out of the page script", async ({ page }) => {
  await visit(page, "/dump", "Dump");

  expect((await rendered(page)).props.script).toBe("</script><script>window.__conformanceXss = true</script>");
  expect(await page.evaluate(() => (window as unknown as { __conformanceXss?: boolean }).__conformanceXss)).toBeUndefined();
});

test("C05 a Link click is an Inertia visit: X-Inertia headers, JSON, no full load", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  const version = (await page.locator('script[data-page="app"]').textContent())?.match(/"version":"([^"]*)"/)?.[1];
  const requests = recordInertiaRequests(page);
  await markDocument(page);

  await page.getByTestId("link-other").click();

  await expect(page).toHaveURL(/\/dump\/other$/);
  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  expect(await documentIsMarked(page)).toBe(true);
  expect(requests).toHaveLength(1);
  expect(requests[0].headers()["x-inertia-version"]).toBe(version);
  const response = await requests[0].response();
  expect(response?.headers()["x-inertia"]).toBe("true");
});

test("C07 back and forward restore pages from history without a request", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  await page.getByTestId("link-other").click();
  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  const requests = recordInertiaRequests(page);

  await page.goBack();
  await expect(page.getByTestId("url")).toHaveText("/dump");
  await page.goForward();
  await expect(page.getByTestId("url")).toHaveText("/dump/other");

  expect(requests).toHaveLength(0);
});

test("C08 preserveState and preserveScroll keep local state and the scroll position", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  await page.getByTestId("local").fill("kept");
  await page.evaluate(() => window.scrollTo(0, 400));
  // A real click would scroll the link into view first.
  await page.getByTestId("link-preserve").dispatchEvent("click");

  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  await expect(page.getByTestId("local")).toHaveValue("kept");
  expect(await page.evaluate(() => window.scrollY)).toBe(400);

  await page.getByTestId("link-dump").dispatchEvent("click");
  await expect(page.getByTestId("url")).toHaveText("/dump");
  await expect(page.getByTestId("local")).toHaveValue("");
  expect(await page.evaluate(() => window.scrollY)).toBe(0);
});

test("C09 remembered state survives navigating away and back", async ({ page }) => {
  await visit(page, "/remember", "Remember");
  await page.getByTestId("note").fill("remember me");
  await page.getByTestId("link-dump").click();
  await expect(page.getByTestId("component")).toHaveText("Dump");

  await page.goBack();

  await expect(page.getByTestId("component")).toHaveText("Remember");
  await expect(page.getByTestId("note")).toHaveValue("remember me");
});

test("C28 an instant visit shows the target component with the shared props before the response", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  let release: () => void = () => {};
  const held = new Promise<void>((resolve) => (release = resolve));
  await page.route("**/form", async (route) => {
    await held;
    await route.continue();
  });

  await page.getByTestId("link-instant").click();

  await expect(page.getByTestId("component")).toHaveText("Form");
  release();
  await expect(page.getByTestId("errors")).toHaveText("{}");
});
