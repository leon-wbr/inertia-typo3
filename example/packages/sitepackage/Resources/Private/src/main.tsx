// Sets up React Fast Refresh when the page is served by TYPO3 instead of
// Vite's index.html. It is a no-op in production builds.
import "@vitejs/plugin-react/preamble";
import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { createRoot } from "react-dom/client";

import "./index.css";

// Lazy, not eager: Vite hoists eager glob imports above the preamble import,
// so the pages would load before Fast Refresh is set up.
const pages = import.meta.glob<{ default: ResolvedComponent }>("./Pages/**/*.tsx");

void createInertiaApp({
  resolve: async (name) => {
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) {
      throw new Error(`Unknown page component: ${name}`);
    }
    return (await page()).default;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
