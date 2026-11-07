import { resolve } from "node:path";

import { svelte } from "@sveltejs/vite-plugin-svelte";
import react from "@vitejs/plugin-react";
import vue from "@vitejs/plugin-vue";
import { defineConfig, type PluginOption } from "vite";

/**
 * One build per official client: CLIENT=react|vue|svelte. Each goes to
 * public/_assets/conformance/<client>/ with its own manifest, which the
 * root view reads (EXT:inertia_conformance, ClientViewHelper).
 */
const client = process.env.CLIENT ?? "react";

const plugins: Record<string, PluginOption> = {
  react: react(),
  vue: vue(),
  svelte: svelte(),
};
const entries: Record<string, string> = {
  react: "clients/react/main.tsx",
  vue: "clients/vue/main.ts",
  svelte: "clients/svelte/main.ts",
};

if (!(client in entries)) {
  throw new Error(`Unknown CLIENT "${client}", expected one of ${Object.keys(entries).join(", ")}`);
}

export default defineConfig({
  plugins: [plugins[client]],
  base: `/_assets/conformance/${client}/`,
  // public/ is TYPO3's web root, not static files for the bundle.
  publicDir: false,
  build: {
    outDir: resolve(import.meta.dirname, `public/_assets/conformance/${client}`),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(import.meta.dirname, entries[client]),
    },
  },
});
