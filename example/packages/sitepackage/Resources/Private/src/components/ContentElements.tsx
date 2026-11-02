import { Link } from "@inertiajs/react";
import parse, { domToReact, Element, type DOMNode } from "html-react-parser";
import type { ComponentType } from "react";

import type { ContentElement } from "../types/inertia";
import Header from "./CTypes/Header";

const components: Record<string, ComponentType<{ data: Record<string, unknown> }>> = {
  header: Header,
};

/**
 * Renders content elements: with a React component where there is one,
 * otherwise from the HTML TYPO3 rendered. Internal links in that HTML
 * become Inertia links.
 */
export default function ContentElements({
  contentElements,
}: {
  contentElements: ContentElement[];
}) {
  return (
    <div className="content-elements space-y-8">
      {contentElements.map((element, index) => {
        const key = String(element.data.uid ?? index);
        const Component = components[element.CType];

        if (Component) {
          return <Component key={key} data={element.data} />;
        }

        return (
          <div key={key} className="prose">
            {parse(element.html ?? "", {
              replace(node: DOMNode) {
                if (!(node instanceof Element) || node.name !== "a") {
                  return;
                }
                const href = node.attribs.href;
                if (!href?.startsWith("/")) {
                  return;
                }
                return <Link href={href}>{domToReact(node.children as DOMNode[])}</Link>;
              },
            })}
          </div>
        );
      })}
    </div>
  );
}
