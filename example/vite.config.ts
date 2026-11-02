import { mkdirSync, rmSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig, type Plugin } from "vite";
import typo3 from "vite-plugin-typo3";

const root = dirname(fileURLToPath(import.meta.url));
const hotFile = resolve(root, "var/vite-dev-server");

/**
 * Keeps the dev server's file watcher out of vendor/, var/ and public/.
 * vendor/leon-wbr/inertia is a symlink to the repository root, which contains
 * example/vendor again: watching it recurses until Node runs out of memory.
 * The sitepackage sources are watched where they live, in packages/.
 */
function ignoredByWatcher(path: string): boolean {
  return ["vendor", "var", "public"].some(
    (dir) => path === `${root}/${dir}` || path.startsWith(`${root}/${dir}/`),
  );
}

/**
 * Writes var/vite-dev-server while the dev server runs, so TYPO3 loads
 * assets from it (config/system/additional.php) and falls back to the
 * production build otherwise.
 */
function devServerFlag(): Plugin {
  const remove = () => rmSync(hotFile, { force: true });

  return {
    name: "typo3-dev-server-flag",
    apply: "serve",
    configureServer(server) {
      server.httpServer?.once("listening", () => {
        mkdirSync(dirname(hotFile), { recursive: true });
        writeFileSync(hotFile, process.env.VITE_SERVER_URI ?? "");
      });
      server.httpServer?.once("close", remove);
      for (const signal of ["SIGINT", "SIGTERM", "exit"] as const) {
        process.once(signal, () => {
          remove();
          if (signal !== "exit") process.exit();
        });
      }
    },
  };
}

// Entrypoints come from Configuration/ViteEntrypoints.json in each
// installed TYPO3 extension (vite-plugin-typo3).
export default defineConfig({
  plugins: [typo3(), react(), tailwindcss(), devServerFlag()],
  resolve: {
    // vite-plugin-typo3 defaults this to true, which breaks resolving the
    // dependencies of packages in pnpm's symlinked node_modules.
    preserveSymlinks: false,
  },
  server: {
    watch: {
      ignored: ignoredByWatcher,
    },
  },
});
