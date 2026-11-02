import { InfiniteScroll } from "@inertiajs/react";

import ContentElements from "../components/ContentElements";
import Layout from "../components/Layout";
import type { ContentElement } from "../types/inertia";

interface Article {
  id: number;
  title: string;
}

/**
 * A scroll prop: <InfiniteScroll> loads the next page when the end of the
 * list comes into view, and the adapter's scroll metadata tells it which
 * page that is.
 */
export default function Articles({
  title,
  contentElements,
  articles,
}: {
  title: string;
  contentElements: ContentElement[];
  articles: { data: Article[]; meta: { currentPage: number; lastPage: number } };
}) {
  return (
    <Layout title={title}>
      <ContentElements contentElements={contentElements} />

      <InfiniteScroll
        data="articles"
        loading={<p className="py-4 text-neutral-500">Loading more articles…</p>}
      >
        <ul data-testid="articles" className="mt-8 flex flex-col gap-3">
          {articles.data.map((article) => (
            <li key={article.id} className="rounded border px-4 py-6">
              {article.title}
            </li>
          ))}
        </ul>
      </InfiniteScroll>
    </Layout>
  );
}
