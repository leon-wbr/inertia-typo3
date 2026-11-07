import { createInertiaApp, router, type ResolvedComponent } from "@inertiajs/svelte";
import { mount } from "svelte";

// The specs drive the client's own router for programmatic visits.
window.conformance = { client: "svelte", router };

const pages = import.meta.glob<ResolvedComponent>("./Pages/*.svelte", { eager: true });

void createInertiaApp({
  resolve: (name) => {
    const page = pages[`./Pages/${name}.svelte`];
    if (!page) {
      throw new Error(`Unknown page component: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    mount(App, { target: el!, props });
  },
});
