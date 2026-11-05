import { expect, type APIRequestContext, type Page, type Request } from "@playwright/test";

export interface InertiaPage {
  component: string;
  props: Record<string, unknown>;
  url: string;
  version: string;
  [key: string]: unknown;
}

/** The page object TYPO3 rendered into a full page load. */
export function pageFromHtml(html: string): InertiaPage {
  const match = /<script data-page="app" type="application\/json">(.*?)<\/script>/s.exec(html);
  if (!match) {
    throw new Error("No Inertia page script in the response");
  }
  return JSON.parse(match[1]) as InertiaPage;
}

/** The current asset version, from a full page load. */
export async function currentVersion(request: APIRequestContext): Promise<string> {
  const response = await request.get("/dump");
  return pageFromHtml(await response.text()).version;
}

/** Headers of an Inertia visit with the current version. */
export async function inertiaHeaders(
  request: APIRequestContext,
  extra: Record<string, string> = {},
): Promise<Record<string, string>> {
  return {
    "X-Inertia": "true",
    "X-Inertia-Version": await currentVersion(request),
    Accept: "text/html, application/xhtml+xml",
    ...extra,
  };
}

/** The DOM contract of the clients: the page object as rendered JSON. */
export async function rendered(page: Page): Promise<{ component: string; url: string; props: Record<string, unknown>; flash: Record<string, unknown> }> {
  return {
    component: (await page.getByTestId("component").textContent()) ?? "",
    url: (await page.getByTestId("url").textContent()) ?? "",
    props: JSON.parse((await page.getByTestId("props").textContent()) ?? "{}"),
    flash: JSON.parse((await page.getByTestId("flash").textContent()) ?? "{}"),
  };
}

export async function renderedProps(page: Page): Promise<Record<string, unknown>> {
  return (await rendered(page)).props;
}

/** Records the Inertia XHR/fetch requests the client sends. */
export function recordInertiaRequests(page: Page): Request[] {
  const requests: Request[] = [];
  page.on("request", (request) => {
    if (request.headers()["x-inertia"] === "true") {
      requests.push(request);
    }
  });
  return requests;
}

/** Marks the document, so a full page load can be told from an Inertia visit. */
export async function markDocument(page: Page): Promise<void> {
  await page.evaluate(() => {
    (window as unknown as { __conformanceMarker: number }).__conformanceMarker = 1;
  });
}

export async function documentIsMarked(page: Page): Promise<boolean> {
  return page.evaluate(() => (window as unknown as { __conformanceMarker?: number }).__conformanceMarker === 1);
}

/** Visits a case page and waits until the client has mounted it. */
export async function visit(page: Page, url: string, component: string): Promise<void> {
  await page.goto(url);
  await expect(page.getByTestId("component")).toHaveText(component);
}
