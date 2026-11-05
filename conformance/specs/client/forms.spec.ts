import { expect, test } from "@playwright/test";

import { recordInertiaRequests, rendered, visit } from "../support";

test("C34 form errors come back through the redirect and are cleared afterwards", async ({ page }) => {
  await visit(page, "/form", "Form");

  await page.getByTestId("submit").click();

  await expect(page.getByTestId("error-name")).toHaveText("The name is required.");
  await expect(page.getByTestId("errors")).toHaveText(JSON.stringify({ name: "The name is required." }));
  await expect(page).toHaveURL(/\/form$/);

  await page.reload();
  await expect(page.getByTestId("errors")).toHaveText("{}");

  await page.getByTestId("name").fill("Ada");
  await page.getByTestId("submit").click();
  await expect(page.getByTestId("flash")).toHaveText(JSON.stringify({ saved: "Ada" }));
  await expect(page.getByTestId("error-name")).toHaveText("");
});

test("C35 an error bag scopes the errors", async ({ page }) => {
  await visit(page, "/form", "Form");
  const requests = recordInertiaRequests(page);

  await page.getByTestId("submit-bag").click();

  await expect(page.getByTestId("error-name")).toHaveText("The name is required.");
  expect(requests[0].headers()["x-inertia-error-bag"]).toBe("contact");
  await expect(page.getByTestId("errors")).toHaveText(JSON.stringify({ contact: { name: "The name is required." } }));
});

test("C36 a PUT that the server redirects is followed with a GET", async ({ page }) => {
  await visit(page, "/dump", "Dump");
  const methods: string[] = [];
  page.on("request", (request) => {
    if (request.headers()["x-inertia"] === "true") methods.push(`${request.method()} ${new URL(request.url()).pathname}`);
  });

  await page.getByTestId("link-put").click();

  await expect(page.getByTestId("url")).toHaveText("/dump?from=put");
  expect(methods[0]).toBe("PUT /_c/method");
});

test("C40 a redirect to a fragment lands on the fragment", async ({ page }) => {
  await visit(page, "/dump", "Dump");

  await page.getByTestId("link-fragment").click();

  await expect(page).toHaveURL(/\/dump\/other#section$/);
  await expect(page.getByTestId("component")).toHaveText("Dump");
  await expect(page.getByTestId("url")).toHaveText(/^\/dump\/other(#section)?$/);
});

test("C41 location() sends the client to another origin", async ({ page }) => {
  await visit(page, "/dump", "Dump");

  await page.getByTestId("link-external").click();

  await expect(page).toHaveURL("http://127.0.0.1:8090/_c/plain");
  await expect(page.locator("#plain")).toHaveText("Plain HTML page");
});

test("C42 flash data arrives once, as the page's flash", async ({ page }) => {
  await visit(page, "/dump", "Dump");

  await page.getByTestId("link-flash").click();

  await expect(page.getByTestId("flash")).toHaveText(JSON.stringify({ message: "Flashed once" }));
  expect((await rendered(page)).props).not.toHaveProperty("message");

  await page.getByTestId("link-other").click();
  await expect(page.getByTestId("url")).toHaveText("/dump/other");
  await expect(page.getByTestId("flash")).toHaveText("{}");
});

test("C44 a 404 on an Inertia visit renders an error component", async ({ page }) => {
  test.fail(true, "TODO.md: error pages through Inertia are missing; the client shows its error modal");
  await visit(page, "/dump", "Dump");

  await page.evaluate(() => window.conformance.router.visit("/does-not-exist"));

  await expect(page.getByTestId("component")).toHaveText("Error", { timeout: 3000 });
});
