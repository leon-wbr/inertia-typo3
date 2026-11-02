#!/usr/bin/env node
/**
 * Inertia parity check: tells us when Inertia ships something this adapter
 * doesn't know about. No dependencies; Node >= 22.
 *
 *   node Build/Scripts/parity.mjs local      offline, part of `ddev verify`
 *   node Build/Scripts/parity.mjs upstream   network, weekly in GitHub Actions
 *
 * `local` extracts the protocol surface (header names, page-object fields)
 * from the installed @inertiajs/core and fails on any name that
 * Build/Parity/parity.json doesn't list. It also checks that TODO.md states
 * the audited versions recorded in parity.json.
 *
 * `upstream` compares the audited versions with the latest releases of
 * inertiajs/inertia-laravel (Packagist) and @inertiajs/core (npm). It also
 * diffs the latest releases' surfaces against parity.json: the client's
 * headers and page fields, and inertia-laravel's Header constants,
 * ResponseFactory methods and src/ files. It prints links to the release
 * notes in between.
 *
 * Exit codes: 0 in sync, 1 drift, 2 error.
 * See TODO.md, "Keeping this list in sync", for what to do when it fails.
 */

import { existsSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gunzipSync } from 'node:zlib';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const PARITY_FILE = join(ROOT, 'Build/Parity/parity.json');
const TODO_FILE = join(ROOT, 'TODO.md');

const LARAVEL = 'inertiajs/inertia-laravel';
const CORE = '@inertiajs/core';
// A generic User-Agent: GitHub's API rejects requests without one.
const USER_AGENT = 'inertia-typo3-parity-check';

// ---------------------------------------------------------------------------
// Extraction: pure functions over file contents, so tests can feed fixtures.

/** Header names the client uses: string literals that look like headers. */
export function extractClientHeaders(distJs) {
  const names = new Set();
  const re = /["'`](X-[A-Za-z][A-Za-z-]*|Purpose|Precognition[A-Za-z-]*)["'`]/gi;
  for (const m of distJs.matchAll(re)) names.add(canonicalHeader(m[1]));
  return [...names].sort();
}

export function canonicalHeader(name) {
  return name
    .toLowerCase()
    .split('-')
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join('-');
}

/**
 * Keys of an exported interface or object type in a .d.ts file, as dotted
 * paths down to `maxDepth`. Keys marked `@internal` are left out, as are the
 * keys nested in them.
 */
export function extractTypeKeys(dts, typeName, maxDepth = 2) {
  const start = new RegExp(`^export (?:interface|type) ${typeName}\\b[^\\n]*\\{\\s*$`, 'm').exec(dts);
  if (!start) throw new Error(`type ${typeName} not found`);
  const lines = dts.slice(start.index + start[0].length).split('\n');
  const keys = [];
  const stack = []; // keys of the enclosing object literals
  let depth = 1;
  let internal = false;
  let skipBelow = Infinity; // skip keys nested deeper than this (inside an @internal key)
  for (const line of lines) {
    if (/@internal/.test(line)) internal = true;
    const key = /^\s*(?:readonly\s+)?([A-Za-z_$][\w$]*)\??\s*:/.exec(line);
    if (key && !line.trim().startsWith('*') && !line.trim().startsWith('//')) {
      if (internal) skipBelow = Math.min(skipBelow, depth);
      else if (depth <= maxDepth && depth <= skipBelow) {
        keys.push([...stack.slice(0, depth - 1), key[1]].join('.'));
      }
      internal = false;
      stack[depth - 1] = key[1];
    }
    for (const ch of line.replace(/\/\/.*$/, '')) {
      if (ch === '{') depth++;
      if (ch === '}') {
        depth--;
        if (depth < skipBelow + 1) skipBelow = Infinity;
      }
    }
    if (depth === 0) break;
  }
  return keys;
}

/** The client's protocol surface, from @inertiajs/core's dist and types. */
export function extractClientSurface({ distJs, typesDts }) {
  const pageFields = extractTypeKeys(typesDts, 'Page', 2).filter(
    // props.* are app props, except the two the protocol defines
    (k) => !k.startsWith('props.') || ['props.errors', 'props.deferred'].includes(k),
  );
  return {
    headers: extractClientHeaders(distJs),
    pageFields: pageFields.sort(),
    scrollPropFields: extractTypeKeys(typesDts, 'ScrollProp', 1).sort(),
    livePropFields: extractTypeKeys(typesDts, 'LiveProp', 1).sort(),
  };
}

/** inertia-laravel's server surface, from its Header.php, ResponseFactory.php and file list. */
export function extractServerSurface({ headerPhp, responseFactoryPhp, srcFiles }) {
  const headerConstants = [...headerPhp.matchAll(/const\s+([A-Z_]+)\s*=\s*'([^']+)'/g)].map(
    (m) => `${m[1]}=${m[2]}`,
  );
  const responseFactoryMethods = [
    ...responseFactoryPhp.matchAll(/^\s+public (?:static )?function (\w+)\s*\(/gm),
  ]
    .map((m) => m[1])
    .filter((name) => !name.startsWith('__'));
  return {
    headerConstants: headerConstants.sort(),
    responseFactoryMethods: [...new Set(responseFactoryMethods)].sort(),
    srcFiles: srcFiles.filter((f) => f.startsWith('src/') && f.endsWith('.php')).sort(),
  };
}

// ---------------------------------------------------------------------------
// Comparison

/** Names in `found` that the declared list doesn't know, and declared names no longer found. */
export function diffNames(found, declared) {
  const known = new Set(declared);
  const seen = new Set(found);
  return {
    unknown: found.filter((n) => !known.has(n)),
    gone: declared.filter((n) => !seen.has(n)),
  };
}

/** Compare the client surface with parity.json's declarations. */
export function compareClient(surface, parity) {
  const declared = parity.client;
  const result = {};
  for (const key of ['headers', 'pageFields', 'scrollPropFields', 'livePropFields']) {
    result[key] = diffNames(surface[key], Object.keys(declared[key]));
  }
  return result;
}

export function compareServer(surface, parity) {
  const recorded = parity.server;
  const result = {};
  for (const key of ['headerConstants', 'responseFactoryMethods', 'srcFiles']) {
    result[key] = diffNames(surface[key], recorded[key]);
  }
  return result;
}

const hasDiff = (cmp, { gone = true } = {}) =>
  Object.values(cmp).some((d) => d.unknown.length > 0 || (gone && d.gone.length > 0));

function formatDiff(title, cmp) {
  const lines = [];
  for (const [key, d] of Object.entries(cmp)) {
    for (const n of d.unknown) lines.push(`  + ${key}: ${n}  (new, not in parity.json)`);
    for (const n of d.gone) lines.push(`  - ${key}: ${n}  (in parity.json, no longer upstream)`);
  }
  return lines.length ? [`${title}:`, ...lines] : [`${title}: no changes`];
}

/** Release notes between two versions. Both CHANGELOG.md files lag behind the releases. */
export function versionLinks(pkg, from, to) {
  const repo = pkg === LARAVEL ? 'inertiajs/inertia-laravel' : 'inertiajs/inertia';
  return [
    `https://github.com/${repo}/compare/v${from}...v${to}`,
    `https://github.com/${repo}/releases`,
  ];
}

/** The audited versions TODO.md's header states, e.g. "`inertiajs/inertia-laravel` 3.7.0". */
export function todoVersions(todo) {
  const find = (pkg) => {
    const m = new RegExp('`' + pkg.replace(/[/@]/g, '\\$&') + '`\\s+v?(\\d+\\.\\d+\\.\\d+)').exec(todo);
    return m ? m[1] : null;
  };
  return { [LARAVEL]: find(LARAVEL), [CORE]: find(CORE) };
}

// ---------------------------------------------------------------------------
// I/O

function readParity(file = PARITY_FILE) {
  return JSON.parse(readFileSync(file, 'utf8'));
}

/**
 * The installed @inertiajs/core, as @inertiajs/react sees it: Node's lookup
 * up the node_modules directories from react's real (pnpm store) path. Not
 * require.resolve(): core's exports map only has an `import` condition.
 */
export function findInstalledCore(exampleDir = join(ROOT, 'example')) {
  let dir = realpathSync(join(exampleDir, 'node_modules/@inertiajs/react'));
  for (;;) {
    const candidate = join(dir, 'node_modules', CORE);
    if (existsSync(join(candidate, 'package.json'))) return candidate;
    const parent = dirname(dir);
    if (parent === dir) throw new Error(`${CORE} not found from ${exampleDir}; run pnpm install in example/`);
    dir = parent;
  }
}

function readCoreDir(dir) {
  return {
    version: JSON.parse(readFileSync(join(dir, 'package.json'), 'utf8')).version,
    distJs: readFileSync(join(dir, 'dist/index.js'), 'utf8'),
    typesDts: readFileSync(join(dir, 'types/types.d.ts'), 'utf8'),
  };
}

/** Files from a .tgz, in memory: npm tarballs are gzipped ustar. */
export function untar(tgz, wanted) {
  const tar = gunzipSync(tgz);
  const files = {};
  for (let off = 0; off + 512 <= tar.length; ) {
    const name = tar.toString('utf8', off, off + 100).replace(/\0.*$/s, '');
    if (!name) break;
    const prefix = tar.toString('utf8', off + 345, off + 500).replace(/\0.*$/s, '');
    const size = parseInt(tar.toString('utf8', off + 124, off + 136).replace(/\0.*$/s, '').trim() || '0', 8);
    const path = prefix ? `${prefix}/${name}` : name;
    if (wanted.includes(path)) files[path] = tar.toString('utf8', off + 512, off + 512 + size);
    off += 512 + Math.ceil(size / 512) * 512;
  }
  return files;
}

function makeFetcher() {
  const headers = { 'User-Agent': USER_AGENT };
  if (process.env.GITHUB_TOKEN) headers.Authorization = `Bearer ${process.env.GITHUB_TOKEN}`;
  const get = async (url) => {
    const res = await fetch(url, { headers: url.includes('github') ? headers : { 'User-Agent': USER_AGENT } });
    if (!res.ok) throw new Error(`${res.status} ${res.statusText} for ${url}`);
    return res;
  };
  return {
    text: async (url) => (await get(url)).text(),
    json: async (url) => (await get(url)).json(),
    buffer: async (url) => Buffer.from(await (await get(url)).arrayBuffer()),
  };
}

export async function latestVersions(http) {
  const packagist = await http.json(`https://repo.packagist.org/p2/${LARAVEL}.json`);
  const stable = packagist.packages[LARAVEL].map((p) => p.version.replace(/^v/, '')).filter((v) =>
    /^\d+\.\d+\.\d+$/.test(v),
  );
  const npm = await http.json(`https://registry.npmjs.org/${CORE.replace('/', '%2f')}/latest`);
  return { [LARAVEL]: stable.sort(compareSemver).at(-1), [CORE]: npm.version };
}

export function compareSemver(a, b) {
  const pa = a.split('.').map(Number);
  const pb = b.split('.').map(Number);
  for (let i = 0; i < 3; i++) if (pa[i] !== pb[i]) return pa[i] - pb[i];
  return 0;
}

export async function fetchServerSurface(http, version) {
  const tag = `v${version}`;
  const raw = (path) => http.text(`https://raw.githubusercontent.com/inertiajs/inertia-laravel/${tag}/${path}`);
  const tree = await http.json(`https://api.github.com/repos/inertiajs/inertia-laravel/git/trees/${tag}?recursive=1`);
  return extractServerSurface({
    headerPhp: await raw('src/Support/Header.php'),
    responseFactoryPhp: await raw('src/ResponseFactory.php'),
    srcFiles: tree.tree.filter((e) => e.type === 'blob').map((e) => e.path),
  });
}

export async function fetchClientSurface(http, version) {
  const tgz = await http.buffer(`https://registry.npmjs.org/${CORE}/-/core-${version}.tgz`);
  const files = untar(tgz, ['package/dist/index.js', 'package/types/types.d.ts']);
  return extractClientSurface({ distJs: files['package/dist/index.js'], typesDts: files['package/types/types.d.ts'] });
}

// ---------------------------------------------------------------------------
// Commands

export function runLocal({ parity, core, todo }) {
  const out = [];
  let drift = false;

  const cmp = compareClient(extractClientSurface(core), parity);
  // Locally only unknown names fail: an older installed client may lack newer names.
  if (hasDiff(cmp, { gone: false })) drift = true;
  out.push(...formatDiff(`Client surface of the installed ${CORE} ${core.version}`, cmp));

  const audited = parity.audited;
  if (core.version !== audited[CORE]) {
    out.push(`Note: installed ${CORE} is ${core.version}, the audit is for ${audited[CORE]}.`);
  }

  const stated = todoVersions(todo);
  for (const pkg of [LARAVEL, CORE]) {
    if (stated[pkg] !== audited[pkg]) {
      drift = true;
      out.push(`TODO.md states ${pkg} ${stated[pkg] ?? '(nothing)'}, parity.json says ${audited[pkg]}.`);
    }
  }

  out.push(drift ? 'DRIFT: see TODO.md, "Keeping this list in sync".' : 'In sync.');
  return { drift, text: out.join('\n') };
}

export async function runUpstream({ parity, http, latest, audited = parity.audited }) {
  latest ??= await latestVersions(http);
  const out = [`Audited: ${LARAVEL} ${audited[LARAVEL]}, ${CORE} ${audited[CORE]} (${parity.audited.date})`];
  out.push(`Latest:  ${LARAVEL} ${latest[LARAVEL]}, ${CORE} ${latest[CORE]}`);
  let drift = false;

  for (const pkg of [LARAVEL, CORE]) {
    if (latest[pkg] !== audited[pkg]) {
      drift = true;
      out.push(`New release: ${pkg} ${audited[pkg]} -> ${latest[pkg]}`);
      for (const link of versionLinks(pkg, audited[pkg], latest[pkg])) out.push(`  ${link}`);
    }
  }

  const server = compareServer(await fetchServerSurface(http, latest[LARAVEL]), parity);
  if (hasDiff(server)) drift = true;
  out.push(...formatDiff(`Server surface of ${LARAVEL} ${latest[LARAVEL]}`, server));

  const client = compareClient(await fetchClientSurface(http, latest[CORE]), parity);
  if (hasDiff(client)) drift = true;
  out.push(...formatDiff(`Client surface of ${CORE} ${latest[CORE]}`, client));

  out.push(drift ? 'DRIFT: see TODO.md, "Keeping this list in sync".' : 'In sync.');
  return { drift, latest, text: out.join('\n') };
}

function parseArgs(argv) {
  const [command, ...rest] = argv;
  const opts = {};
  for (const arg of rest) {
    const m = /^--([\w-]+)(?:=(.*))?$/.exec(arg);
    if (!m) throw new Error(`unknown argument ${arg}`);
    opts[m[1]] = m[2] ?? true;
  }
  return { command, opts };
}

const USAGE = `Usage:
  parity.mjs local [--core-dir=DIR] [--todo=FILE]
  parity.mjs upstream [--audited-laravel=X.Y.Z] [--audited-core=X.Y.Z] [--report=FILE]
  parity.mjs record   store the latest versions and server surface in parity.json

  --core-dir         an unpacked @inertiajs/core (default: the example's)
  --audited-*        pretend the audit is for these versions, to see what drift looks like
  --report=FILE      also write the output as Markdown, for the tracking issue`;

async function main() {
  const { command, opts } = parseArgs(process.argv.slice(2));
  const parity = readParity();
  let result;
  if (command === 'local') {
    const core = readCoreDir(opts['core-dir'] ? resolve(opts['core-dir']) : findInstalledCore());
    result = runLocal({ parity, core, todo: readFileSync(opts.todo ? resolve(opts.todo) : TODO_FILE, 'utf8') });
  } else if (command === 'upstream') {
    const audited = {
      ...parity.audited,
      ...(opts['audited-laravel'] && { [LARAVEL]: opts['audited-laravel'] }),
      ...(opts['audited-core'] && { [CORE]: opts['audited-core'] }),
    };
    result = await runUpstream({ parity, http: makeFetcher(), audited });
    if (opts.report) {
      writeFileSync(
        opts.report,
        [
          'The weekly parity check found upstream Inertia changes the adapter has not been audited against.',
          '',
          '```',
          result.text,
          '```',
          '',
          'What to do: see "Keeping this list in sync" in TODO.md. This issue is updated by `.github/workflows/parity.yml`.',
          '',
        ].join('\n'),
      );
    }
  } else if (command === 'record') {
    // After auditing a new release: store its versions and server surface.
    // Client names are not recorded: classify each new one by hand in parity.json.
    const http = makeFetcher();
    const latest = await latestVersions(http);
    parity.audited = { date: new Date().toLocaleDateString('en-CA'), ...latest };
    parity.server = await fetchServerSurface(http, latest[LARAVEL]);
    writeFileSync(PARITY_FILE, JSON.stringify(parity, null, 2) + '\n');
    result = { drift: false, text: `Recorded ${LARAVEL} ${latest[LARAVEL]} and ${CORE} ${latest[CORE]} in parity.json. Update TODO.md's header to match.` };
  } else {
    console.error(USAGE);
    process.exit(2);
  }
  console.log(result.text);
  process.exit(result.drift ? 1 : 0);
}

if (process.argv[1] && realpathSync(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(`parity: ${err.message}`);
    process.exit(2);
  });
}
