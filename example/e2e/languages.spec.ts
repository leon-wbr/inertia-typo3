import { expect, test } from "@playwright/test";

import { initialPage } from "./helpers";

test("the second language has its own pages, menu and content", async ({ page }) => {
  await page.goto("/features");
  await page
    .getByRole("navigation", { name: "Language" })
    .getByRole("link", { name: "DE" })
    .click();

  await expect(page).toHaveURL("/de/funktionen");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Funktionen");
  await expect(page.getByRole("navigation", { name: "Main" }).getByRole("link")).toHaveText([
    "Funktionen",
    "Kontakt",
  ]);

  const data = await initialPage(page);
  expect(data.url).toBe("/de/funktionen");
  expect(data.props).toMatchObject({ site: { language: "de" } });
});

test("German content links stay in German", async ({ page }) => {
  await page.goto("/de/");
  await expect(
    page.getByRole("heading", { name: "Willkommen bei TYPO3 und Inertia" }),
  ).toBeVisible();

  await page.getByRole("main").getByRole("link", { name: "Funktionen" }).click();
  await expect(page).toHaveURL("/de/funktionen");
});
