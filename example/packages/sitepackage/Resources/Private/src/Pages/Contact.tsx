import { Form, usePage } from "@inertiajs/react";

import ContentElements from "../components/ContentElements";
import Layout from "../components/Layout";
import type { ContentElement } from "../types/inertia";

/**
 * A form submitted through Inertia. The server answers with a redirect
 * back to this page, carrying the result in the query string.
 */
export default function Contact({
  title,
  contentElements,
  sent,
  errors,
}: {
  title: string;
  contentElements: ContentElement[];
  sent: string | null;
  errors: { name?: string };
}) {
  const action = usePage().url.split("?")[0];

  return (
    <Layout title={title}>
      <ContentElements contentElements={contentElements} />

      {sent ? (
        <p role="status" className="mt-8 text-lg">
          Thanks, {sent}!
        </p>
      ) : (
        <Form method="post" action={action} className="mt-8 flex flex-col gap-3">
          {({ processing }) => (
            <>
              <label className="flex flex-col gap-1">
                Name
                <input name="name" className="rounded border px-3 py-2" />
              </label>
              {errors.name && (
                <p role="alert" className="text-red-700">
                  {errors.name}
                </p>
              )}
              <button
                type="submit"
                disabled={processing}
                className="self-start rounded bg-neutral-900 px-4 py-2 text-white"
              >
                Send
              </button>
            </>
          )}
        </Form>
      )}
    </Layout>
  );
}
