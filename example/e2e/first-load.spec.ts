import { expect, test } from "@playwright/test";

import { initialPage } from "./helpers";

test("the first load renders the page from the JSON page script", async ({ page }) => {
  const response = await page.goto("/");
  expect(response?.status()).toBe(200);
  expect(response?.headers()["vary"]).toContain("X-Inertia");

  const data = await initialPage(page);
  expect(data.component).toBe("Default");
  expect(data.url).toBe("/");
  expect(data).not.toHaveProperty("rootView");
  expect(data.props).toMatchObject({
    title: "Home",
    site: { title: "TYPO3 + Inertia", language: "en" },
  });

  // Inertia 3 markup: an empty mount point, no data-page attribute.
  await expect(page.locator("#app")).not.toHaveAttribute("data-page");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Home");
  await expect(page.getByRole("heading", { name: "Welcome to TYPO3 and Inertia" })).toBeVisible();
  await expect(page).toHaveTitle("Home – TYPO3 + Inertia");
});

test("the navigation comes from the page tree", async ({ page }) => {
  await page.goto("/");
  const nav = page.getByRole("navigation", { name: "Main" });

  await expect(nav.getByRole("link")).toHaveText(["Features", "Articles", "Contact"]);
  // Hidden pages and pages hidden in menus stay out of the navigation.
  await expect(nav.getByRole("link", { name: "Hidden page" })).toHaveCount(0);
  await expect(nav.getByRole("link", { name: "Not in menu" })).toHaveCount(0);

  const response = await page.goto("/not-in-menu");
  expect(response?.status()).toBe(200);
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Not in menu");
});
