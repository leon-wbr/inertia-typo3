import { expect, test } from "@playwright/test";

test("Inertia links fetch JSON instead of reloading the page", async ({ page }) => {
  await page.goto("/");
  await page.evaluate(() => ((window as unknown as { marker: string }).marker = "same document"));

  const visit = page.waitForResponse(
    (response) => response.url().endsWith("/features") && response.request().method() === "GET",
  );
  await page
    .getByRole("navigation", { name: "Main" })
    .getByRole("link", { name: "Features" })
    .click();
  const response = await visit;

  expect(await response.request().headerValue("X-Inertia")).toBe("true");
  expect(response.headers()["content-type"]).toContain("application/json");
  expect(response.headers()["x-inertia"]).toBe("true");
  expect(await response.json()).toMatchObject({ component: "Features", url: "/features" });

  await expect(page).toHaveURL("/features");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Features");
  expect(await page.evaluate(() => (window as unknown as { marker?: string }).marker)).toBe(
    "same document",
  );
});

test("links inside TYPO3-rendered content are Inertia links too", async ({ page }) => {
  await page.goto("/");
  const visit = page.waitForResponse((response) => response.url().endsWith("/contact"));
  await page.getByRole("main").getByRole("link", { name: "get in touch" }).click();

  expect(await (await visit).request().headerValue("X-Inertia")).toBe("true");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Contact");
});
