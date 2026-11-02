import { expect, test } from "@playwright/test";

import { initialPage } from "./helpers";

test("<InfiniteScroll> loads page 2 and merges it", async ({ page }) => {
  await page.goto("/articles");

  const data = await initialPage(page);
  expect(data.scrollProps).toEqual({
    articles: { pageName: "page", previousPage: null, nextPage: 2, currentPage: 1, reset: false },
  });
  expect(data.mergeProps).toEqual(["articles.data"]);

  const articles = page.getByTestId("articles").getByRole("listitem");
  await expect(articles.first()).toHaveText("Article 1");

  const secondPage = page.waitForResponse(async (response) => {
    const request = response.request();
    return (
      new URL(response.url()).searchParams.get("page") === "2" &&
      (await request.headerValue("X-Inertia-Partial-Data")) === "articles"
    );
  });
  await articles.last().scrollIntoViewIfNeeded();
  const response = await secondPage;

  expect(await response.request().headerValue("X-Inertia-Infinite-Scroll-Merge-Intent")).toBe(
    "append",
  );
  const json = await response.json();
  expect(json.props.articles.data.map((article: { id: number }) => article.id)).toEqual([
    11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
  ]);
  expect(json.scrollProps.articles).toMatchObject({ previousPage: 1, nextPage: 3, currentPage: 2 });

  // Page 2 is appended to page 1, not swapped in.
  await expect(articles.nth(10)).toHaveText("Article 11");
  await expect(articles.first()).toHaveText("Article 1");
  expect(await articles.count()).toBeGreaterThanOrEqual(20);
});
