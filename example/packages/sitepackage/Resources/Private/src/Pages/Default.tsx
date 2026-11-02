import ContentElements from "../components/ContentElements";
import Layout from "../components/Layout";
import type { ContentElement } from "../types/inertia";

export default function Default({
  title,
  contentElements,
}: {
  title: string;
  contentElements: ContentElement[];
}) {
  return (
    <Layout title={title}>
      <ContentElements contentElements={contentElements} />
    </Layout>
  );
}
