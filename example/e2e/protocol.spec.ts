import { expect, test } from "@playwright/test";

import { currentVersion } from "./helpers";

test("an outdated asset version gets a 409 with X-Inertia-Location", async ({ page, request }) => {
  const version = await currentVersion(page);

  const response = await request.get("/features?page=2", {
    headers: { "X-Inertia": "true", "X-Inertia-Version": `${version}-outdated` },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(409);
  expect(response.headers()["x-inertia-location"]).toMatch(/\/features\?page=2$/);
});

test("the url keeps the query string", async ({ page, request }) => {
  const version = await currentVersion(page);
  const response = await request.get("/features?page=2&utm_source=test", {
    headers: { "X-Inertia": "true", "X-Inertia-Version": version },
  });

  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.url).toBe("/features?page=2&utm_source=test");
  expect(data).not.toHaveProperty("rootView");
});

test("a partial reload returns only the requested props", async ({ page, request }) => {
  const version = await currentVersion(page);
  const response = await request.get("/features", {
    headers: {
      "X-Inertia": "true",
      "X-Inertia-Version": version,
      "X-Inertia-Partial-Component": "Features",
      "X-Inertia-Partial-Data": "title,pagination",
    },
  });

  const data = await response.json();
  expect(Object.keys(data.props).sort()).toEqual(["pagination", "title"]);
  expect(data.props.title).toBe("Features");
});

test("PUT redirects become 303 See Other", async ({ page, request }) => {
  const version = await currentVersion(page);
  const response = await request.put("/contact", {
    headers: { "X-Inertia": "true", "X-Inertia-Version": version },
    data: { name: "Grace" },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
  expect(response.headers()["location"]).toBe("/contact?sent=Grace");
});
