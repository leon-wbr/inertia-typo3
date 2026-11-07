import { createInertiaApp, router } from "@inertiajs/vue3";
import { createApp, h, type DefineComponent } from "vue";

// The specs drive the client's own router for programmatic visits.
window.conformance = { client: "vue", router };

const pages = import.meta.glob<{ default: DefineComponent }>("./Pages/*.vue", { eager: true });

void createInertiaApp({
  resolve: (name) => {
    const page = pages[`./Pages/${name}.vue`];
    if (!page) {
      throw new Error(`Unknown page component: ${name}`);
    }
    return page.default;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
