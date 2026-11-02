import { Form, usePage } from "@inertiajs/react";

import ContentElements from "../components/ContentElements";
import Layout from "../components/Layout";
import type { ContentElement } from "../types/inertia";

/**
 * A form submitted through Inertia. The server validates it and redirects
 * back: validation errors arrive as the `errors` prop (scoped to the
 * "contact" error bag), a success message as flash data.
 */
export default function Contact({
  title,
  contentElements,
}: {
  title: string;
  contentElements: ContentElement[];
}) {
  const { url, flash } = usePage();
  const action = url.split("?")[0];

  return (
    <Layout title={title}>
      <ContentElements contentElements={contentElements} />

      {flash.success ? (
        <p role="status" className="mt-8 text-lg">
          {flash.success}
        </p>
      ) : (
        <Form method="post" action={action} errorBag="contact" className="mt-8 flex flex-col gap-3">
          {({ errors, processing }) => (
            <>
              <label className="flex flex-col gap-1">
                Name
                <input
                  name="name"
                  aria-invalid={errors.name ? true : undefined}
                  className="rounded border px-3 py-2"
                />
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
