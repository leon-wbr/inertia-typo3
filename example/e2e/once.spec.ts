import { expect, test } from "@playwright/test";

import { initialPage } from "./helpers";

test("a once prop is not sent again on the next visit", async ({ page }) => {
  await page.goto("/");

  const data = await initialPage(page);
  expect(data.onceProps).toEqual({ "labels-en": { prop: "labels", expiresAt: null } });
  expect(data.props).toHaveProperty("labels");
  const footer = page.getByTestId("labels");
  await expect(footer).toHaveText("An example of TYPO3 and Inertia.");
  const loadedAt = await footer.getAttribute("title");

  const visit = page.waitForResponse((response) => response.url().endsWith("/features"));
  await page
    .getByRole("navigation", { name: "Main" })
    .getByRole("link", { name: "Features" })
    .click();
  const response = await visit;

  // The client lists the once props it has, and the server leaves them out.
  expect(await response.request().headerValue("X-Inertia-Except-Once-Props")).toBe("labels-en");
  const json = await response.json();
  expect(json.props).not.toHaveProperty("labels");
  expect(json.onceProps).toEqual({ "labels-en": { prop: "labels", expiresAt: null } });

  // The page still shows the labels it got with the first visit.
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Features");
  await expect(footer).toHaveText("An example of TYPO3 and Inertia.");
  await expect(footer).toHaveAttribute("title", loadedAt ?? "");
});
