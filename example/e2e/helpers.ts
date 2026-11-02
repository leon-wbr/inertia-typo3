import { expect, type Page } from "@playwright/test";

export interface InertiaPage {
  component: string;
  props: Record<string, unknown>;
  url: string;
  version: string;
  [key: string]: unknown;
}

/** Reads the page object TYPO3 rendered into the first response. */
export async function initialPage(page: Page): Promise<InertiaPage> {
  const script = page.locator('script[data-page="app"][type="application/json"]');
  await expect(script).toHaveCount(1);
  return JSON.parse((await script.textContent()) ?? "") as InertiaPage;
}

/** The asset version of the current build, from a full page load. */
export async function currentVersion(page: Page): Promise<string> {
  await page.goto("/");
  return (await initialPage(page)).version;
}
