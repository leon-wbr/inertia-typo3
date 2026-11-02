import { expect, test } from "@playwright/test";

test("a form POST is answered with a 303 redirect", async ({ page }) => {
  await page.goto("/contact");

  const redirects: number[] = [];
  page.on("response", (response) => {
    if (response.request().method() === "POST" && response.url().endsWith("/contact")) {
      redirects.push(response.status());
    }
  });
  const followUp = page.waitForResponse(
    (response) =>
      response.request().method() === "GET" && response.url().endsWith("/contact?sent=Ada"),
  );

  await page.getByLabel("Name").fill("Ada");
  await page.getByRole("button", { name: "Send" }).click();
  const response = await followUp;

  expect(redirects).toEqual([303]);
  expect(await response.json()).toMatchObject({ component: "Contact", url: "/contact?sent=Ada" });
  await expect(page.getByRole("status")).toHaveText("Thanks, Ada!");
  await expect(page).toHaveURL("/contact?sent=Ada");
});

test("the POST response itself is a 303 See Other", async ({ request }) => {
  const response = await request.post("/contact", {
    headers: { "X-Inertia": "true" },
    data: { name: "Ada" },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
  expect(response.headers()["location"]).toBe("/contact?sent=Ada");
});

test("a form error comes back through the redirect", async ({ page }) => {
  await page.goto("/contact");
  await page.getByRole("button", { name: "Send" }).click();

  await expect(page.getByRole("alert")).toHaveText("Please enter your name.");
  await expect(page).toHaveURL("/contact?error=name");
});
