// TypeScript 7 (the native compiler) has no JavaScript compiler API, but
// typescript-eslint 8 needs one (peer typescript ">=4.8.4 <6.1.0").
// `pnpm typecheck` runs TypeScript 7; the lint toolchain gets the
// TypeScript 6 API package as its own dependency instead of the peer.
// Drop this file once typescript-eslint supports TypeScript 7.
const TYPESCRIPT_API = "npm:@typescript/typescript6@^6.0.2";

function readPackage(pkg) {
  const isLintToolchain =
    pkg.name === "typescript-eslint" ||
    pkg.name === "ts-api-utils" ||
    pkg.name.startsWith("@typescript-eslint/");

  if (isLintToolchain && pkg.peerDependencies?.typescript) {
    delete pkg.peerDependencies.typescript;
    if (pkg.peerDependenciesMeta) delete pkg.peerDependenciesMeta.typescript;
    pkg.dependencies = { ...pkg.dependencies, typescript: TYPESCRIPT_API };
  }

  return pkg;
}

module.exports = { hooks: { readPackage } };
