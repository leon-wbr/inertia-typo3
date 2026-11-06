import { createInertiaApp, router, type ResolvedComponent } from "@inertiajs/react";
import { createRoot } from "react-dom/client";

// The specs drive the client's own router for programmatic visits.
window.conformance = { client: "react", router };

const pages = import.meta.glob<{ default: ResolvedComponent }>("./Pages/*.tsx", { eager: true });

void createInertiaApp({
  resolve: (name) => {
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) {
      throw new Error(`Unknown page component: ${name}`);
    }
    return page.default;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
