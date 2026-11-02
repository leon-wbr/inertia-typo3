import { Deferred, router } from "@inertiajs/react";

import ContentElements from "../components/ContentElements";
import Layout from "../components/Layout";
import type { ContentElement } from "../types/inertia";

interface Props {
  title: string;
  contentElements: ContentElement[];
  stats?: { contentElements: number; generatedAt: string };
  details?: { requestedWith: string };
  items: { id: number; title: string }[];
  pagination: { page: number; hasMore: boolean };
}

/**
 * Shows the Inertia 3 prop types the adapter supports: deferred props
 * (loaded right after the page), optional props (only loaded on request)
 * and merge props (appended on "Load more").
 */
export default function Features({
  title,
  contentElements,
  stats,
  details,
  items,
  pagination,
}: Props) {
  return (
    <Layout title={title}>
      <ContentElements contentElements={contentElements} />

      <section aria-labelledby="deferred" className="mt-10">
        <h2 id="deferred" className="text-xl font-semibold">
          Deferred props
        </h2>
        <Deferred data="stats" fallback={<p>Loading stats…</p>}>
          <p data-testid="stats">
            {stats?.contentElements} content elements, generated at {stats?.generatedAt}
          </p>
        </Deferred>
      </section>

      <section aria-labelledby="optional" className="mt-10">
        <h2 id="optional" className="text-xl font-semibold">
          Optional props
        </h2>
        {details ? (
          <p data-testid="details">Loaded with: {details.requestedWith}</p>
        ) : (
          <button
            type="button"
            className="rounded border px-3 py-1"
            onClick={() => router.reload({ only: ["details"] })}
          >
            Load details
          </button>
        )}
      </section>

      <section aria-labelledby="merge" className="mt-10">
        <h2 id="merge" className="text-xl font-semibold">
          Merge props
        </h2>
        <ul data-testid="items" className="list-disc pl-6">
          {items.map((item) => (
            <li key={item.id}>{item.title}</li>
          ))}
        </ul>
        {pagination.hasMore && (
          <button
            type="button"
            className="mt-2 rounded border px-3 py-1"
            onClick={() =>
              router.reload({
                only: ["items", "pagination"],
                data: { page: pagination.page + 1 },
              })
            }
          >
            Load more
          </button>
        )}
      </section>
    </Layout>
  );
}
