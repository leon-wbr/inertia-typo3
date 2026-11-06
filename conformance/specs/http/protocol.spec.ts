import { expect, test } from "@playwright/test";

import { currentVersion, inertiaHeaders, pageFromHtml } from "../support";

/**
 * Server rules of the protocol, checked with raw requests. The case IDs are
 * those of docs/plans/2026-11-05-compat-and-native.md. A test.fail() marks
 * a known gap; fixing it makes the test pass unexpectedly, which fails the
 * run until the marker is removed.
 */

function varyValues(header: string | undefined): string[] {
  return (header ?? "")
    .split(",")
    .map((value) => value.trim().toLowerCase())
    .filter(Boolean);
}

test("C03 the page url keeps the query string", async ({ request }) => {
  const response = await request.get("/dump?b=2&a=1", { headers: await inertiaHeaders(request) });
  expect((await response.json()).url).toBe("/dump?b=2&a=1");

  const html = await request.get("/dump?b=2&a=1");
  expect(pageFromHtml(await html.text()).url).toBe("/dump?b=2&a=1");
});

test("C04 empty errors are an object, on full loads and Inertia visits", async ({ request }) => {
  const html = pageFromHtml(await (await request.get("/dump")).text());
  expect(html.props.errors).toEqual({});

  const response = await request.get("/dump", { headers: await inertiaHeaders(request) });
  expect(await response.text()).toContain('"errors":{}');
});

test("C05 an Inertia visit gets the page object as JSON with X-Inertia: true", async ({ request }) => {
  const response = await request.get("/dump", { headers: await inertiaHeaders(request) });

  expect(response.status()).toBe(200);
  expect(response.headers()["x-inertia"]).toBe("true");
  expect(response.headers()["content-type"]).toContain("application/json");
  const page = await response.json();
  expect(page).toMatchObject({ component: "Dump", url: "/dump", version: await currentVersion(request) });
  expect(page.props).toMatchObject({ title: "Dump", app: { name: "conformance" } });
});

test("C06 every response varies on X-Inertia", async ({ request }) => {
  const html = await request.get("/dump");
  expect(varyValues(html.headers()["vary"])).toContain("x-inertia");

  const json = await request.get("/dump", { headers: await inertiaHeaders(request) });
  expect(varyValues(json.headers()["vary"])).toContain("x-inertia");
});

test("C10 a stale version gets a 409 with X-Inertia-Location and the current version", async ({ request }) => {
  const version = await currentVersion(request);
  const response = await request.get("/dump/other?x=1", {
    headers: { "X-Inertia": "true", "X-Inertia-Version": `${version}-stale` },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(409);
  expect(response.headers()["x-inertia-location"]).toMatch(/\/dump\/other\?x=1$/);
  expect(response.headers()["x-inertia-version"]).toBe(version);
  expect(response.headers()["x-inertia"]).toBeUndefined();
});

test("C12 non-GET requests skip the version check", async ({ request }) => {
  const response = await request.post("/_c/form", {
    headers: { "X-Inertia": "true", "X-Inertia-Version": "stale" },
    data: { name: "Ada" },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
});

test("C16 partial reloads follow nested dot paths in only and except", async ({ request }) => {
  const only = await request.get("/dump", {
    headers: await inertiaHeaders(request, {
      "X-Inertia-Partial-Component": "Dump",
      "X-Inertia-Partial-Data": "nested.a.b",
    }),
  });
  expect((await only.json()).props.nested).toEqual({ a: { b: 1 } });

  const except = await request.get("/dump", {
    headers: await inertiaHeaders(request, {
      "X-Inertia-Partial-Component": "Dump",
      "X-Inertia-Partial-Data": "nested",
      "X-Inertia-Partial-Except": "nested.a.c",
    }),
  });
  expect((await except.json()).props.nested).toEqual({ a: { b: 1 }, d: 3 });
});

test("C17 a partial request for another component returns all props", async ({ request }) => {
  const response = await request.get("/dump", {
    headers: await inertiaHeaders(request, {
      "X-Inertia-Partial-Component": "Other",
      "X-Inertia-Partial-Data": "title",
    }),
  });
  const props = (await response.json()).props;

  expect(props).toHaveProperty("nested");
  expect(props).toHaveProperty("expensive");
  expect(props).not.toHaveProperty("lazy");
});

test("C18 always props and errors are in every partial reload", async ({ request }) => {
  const response = await request.get("/dump", {
    headers: await inertiaHeaders(request, {
      "X-Inertia-Partial-Component": "Dump",
      "X-Inertia-Partial-Data": "title",
    }),
  });

  expect(Object.keys((await response.json()).props).sort()).toEqual(["always", "errors", "title"]);
});

test("C32 a prefetch gets a fragment redirect unchanged", async ({ request }) => {
  const response = await request.get("/_c/fragment", {
    headers: await inertiaHeaders(request, { Purpose: "prefetch" }),
    maxRedirects: 0,
  });

  expect(response.status()).toBe(302);
  expect(response.headers()["location"]).toBe("/dump/other#section");
});

// inertiaHeaders() loads a page to read the asset version, and that page
// load would use up the flash data, so these cases build the headers first.

test("C33 without a prefetch, flash data reaches the next visit", async ({ request }) => {
  const headers = await inertiaHeaders(request);
  await request.post("/_c/flash", { headers, maxRedirects: 0 });

  const visit = await request.get("/dump", { headers });

  expect((await visit.json()).flash).toEqual({ message: "Flashed once" });
});

test("C33 a prefetch leaves flash data in the session", async ({ request }) => {
  const headers = await inertiaHeaders(request);
  await request.post("/_c/flash", { headers, maxRedirects: 0 });

  const prefetch = await request.get("/dump", { headers: { ...headers, Purpose: "prefetch" } });
  expect((await prefetch.json()).flash).toBeUndefined();
  const visit = await request.get("/dump", { headers });

  expect((await visit.json()).flash).toEqual({ message: "Flashed once" });
});

test("C33 a prefetch leaves validation errors in the session", async ({ request }) => {
  const headers = await inertiaHeaders(request);
  await request.post("/_c/form", { headers, data: { name: "" }, maxRedirects: 0 });

  const prefetch = await request.get("/form", { headers: { ...headers, Purpose: "prefetch" } });
  expect((await prefetch.json()).props.errors).toEqual({});
  const visit = await request.get("/form", { headers });

  expect((await visit.json()).props.errors).toEqual({ name: "The name is required." });
});

for (const method of ["PUT", "PATCH", "DELETE"]) {
  test(`C36 a 302 after ${method} becomes a 303`, async ({ request }) => {
    const response = await request.fetch("/_c/method", {
      method,
      headers: await inertiaHeaders(request),
      maxRedirects: 0,
    });

    expect(response.status()).toBe(303);
    expect(response.headers()["location"]).toBe(`/dump?from=${method.toLowerCase()}`);
  });
}

test("C36 a 302 after POST stays a 302", async ({ request }) => {
  const response = await request.post("/_c/method", { headers: await inertiaHeaders(request), maxRedirects: 0 });

  expect(response.status()).toBe(302);
});

test("C37 a 302 after QUERY becomes a 303", async ({ request }) => {
  const response = await request.fetch("/_c/method", {
    method: "QUERY",
    headers: { ...(await inertiaHeaders(request)), "Content-Type": "application/json" },
    data: { q: "x" },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
});

test("C38 an empty 200 redirects back to the Referer", async ({ request }) => {
  const response = await request.post("/_c/empty", {
    headers: await inertiaHeaders(request, { Referer: "http://localhost:8090/form" }),
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
  expect(response.headers()["location"]).toBe("http://localhost:8090/form");
});

test("C39 responses the middleware replaces keep Vary: X-Inertia", async ({ request }) => {
  const empty = await request.post("/_c/empty", { headers: await inertiaHeaders(request), maxRedirects: 0 });
  expect(varyValues(empty.headers()["vary"])).toContain("x-inertia");

  const fragment = await request.post("/_c/fragment", { headers: await inertiaHeaders(request), maxRedirects: 0 });
  expect(varyValues(fragment.headers()["vary"])).toContain("x-inertia");
});

test("C40 a redirect to a fragment gets a 409 with X-Inertia-Redirect", async ({ request }) => {
  const response = await request.get("/_c/fragment", { headers: await inertiaHeaders(request), maxRedirects: 0 });

  expect(response.status()).toBe(409);
  expect(response.headers()["x-inertia-redirect"]).toBe("/dump/other#section");

  const plain = await request.get("/_c/fragment", { maxRedirects: 0 });
  expect(plain.status()).toBe(302);
});

test("C41 location() answers an Inertia visit with a 409 and X-Inertia-Location", async ({ request }) => {
  const response = await request.get("/_c/external", { headers: await inertiaHeaders(request), maxRedirects: 0 });

  expect(response.status()).toBe(409);
  expect(response.headers()["x-inertia-location"]).toBe("http://127.0.0.1:8090/_c/plain");

  const plain = await request.get("/_c/external", { maxRedirects: 0 });
  expect(plain.status()).toBe(302);
  expect(plain.headers()["location"]).toBe("http://127.0.0.1:8090/_c/plain");
});

test("C43 a multipart PUT reaches the handler with its fields and file", async ({ request }) => {
  test.fail(true, "TODO.md bug 5: multipart PUT bodies are not parsed");
  const headers = await inertiaHeaders(request);
  await request.put("/_c/upload", {
    headers,
    multipart: { name: "Ada", avatar: { name: "avatar.txt", mimeType: "text/plain", buffer: Buffer.from("x") } },
    maxRedirects: 0,
  });
  const page = await (await request.get("/dump", { headers })).json();

  expect(page.flash).toEqual({ upload: { method: "PUT", name: "Ada", file: "avatar.txt" } });
});

test("C43 a multipart POST with _method=put is handled as a PUT", async ({ request }) => {
  test.fail(true, "TODO.md bug 5: _method is not honoured");
  const headers = await inertiaHeaders(request);
  await request.post("/_c/upload", {
    headers,
    multipart: { _method: "put", name: "Ada", avatar: { name: "avatar.txt", mimeType: "text/plain", buffer: Buffer.from("x") } },
    maxRedirects: 0,
  });
  const page = await (await request.get("/dump", { headers })).json();

  expect(page.flash).toEqual({ upload: { method: "PUT", name: "Ada", file: "avatar.txt" } });
});
