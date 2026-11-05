import type { router } from "@inertiajs/core";

declare global {
  interface Window {
    /** Set by every client: its name and its router, which the specs drive. */
    conformance: { client: string; router: typeof router };
  }
}

export {};
