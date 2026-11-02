import { expect, test } from "@playwright/test";

test("a form POST is answered with a 303 redirect and a flash message", async ({ page }) => {
  await page.goto("/contact");

  const redirects: number[] = [];
  page.on("response", (response) => {
    if (response.request().method() === "POST" && response.url().endsWith("/contact")) {
      redirects.push(response.status());
    }
  });
  const followUp = page.waitForResponse(
    (response) => response.request().method() === "GET" && response.url().endsWith("/contact"),
  );

  await page.getByLabel("Name").fill("Ada");
  await page.getByRole("button", { name: "Send" }).click();
  const response = await followUp;

  expect(redirects).toEqual([303]);
  expect(await response.json()).toMatchObject({
    component: "Contact",
    url: "/contact",
    flash: { success: "Thanks, Ada!" },
  });
  await expect(page.getByRole("status")).toHaveText("Thanks, Ada!");
  await expect(page).toHaveURL("/contact");

  // Flash data is shown once: the next visit has none.
  await page.reload();
  await expect(page.getByRole("status")).toHaveCount(0);
  await expect(page.getByLabel("Name")).toBeVisible();
});

test("the POST response itself is a 303 See Other", async ({ request }) => {
  const response = await request.post("/contact", {
    headers: { "X-Inertia": "true" },
    data: { name: "Ada" },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
  expect(response.headers()["location"]).toBe("/contact");
});

test("validation errors appear after the redirect and clear after a fix", async ({ page }) => {
  await page.goto("/contact");

  const failed = page.waitForResponse(
    (response) => response.request().method() === "GET" && response.url().endsWith("/contact"),
  );
  await page.getByRole("button", { name: "Send" }).click();
  const response = await failed;

  // The error bag header scopes the errors to the form.
  expect(await response.request().headerValue("X-Inertia-Error-Bag")).toBe("contact");
  expect((await response.json()).props.errors).toEqual({
    contact: { name: "Please enter your name." },
  });
  await expect(page.getByRole("alert")).toHaveText("Please enter your name.");
  await expect(page.getByLabel("Name")).toHaveAttribute("aria-invalid", "true");
  await expect(page).toHaveURL("/contact");

  // The errors were read once: a reload doesn't bring them back.
  await page.reload();
  await expect(page.getByRole("alert")).toHaveCount(0);

  await page.getByRole("button", { name: "Send" }).click();
  await expect(page.getByRole("alert")).toHaveText("Please enter your name.");

  await page.getByLabel("Name").fill("Grace");
  await page.getByRole("button", { name: "Send" }).click();
  await expect(page.getByRole("status")).toHaveText("Thanks, Grace!");
  await expect(page.getByRole("alert")).toHaveCount(0);
});
