// Tests for parity.mjs: `node --test Build/Scripts/`. Offline: upstream
// registries are faked from parity.json, then changed to simulate drift.

import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

import {
  extractClientSurface,
  extractTypeKeys,
  runLocal,
  runUpstream,
  todoVersions,
  untar,
} from './parity.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'parity.mjs');
const parity = JSON.parse(readFileSync(join(HERE, '../Parity/parity.json'), 'utf8'));
const LARAVEL = 'inertiajs/inertia-laravel';
const CORE = '@inertiajs/core';

// ---------------------------------------------------------------------------
// Fakes built from parity.json, so "in sync" means "matches what we declared".

function fakeTypes({ pageFields = Object.keys(parity.client.pageFields) } = {}) {
  const top = pageFields.filter((f) => !f.includes('.'));
  const nested = (parent) => pageFields.filter((f) => f.startsWith(parent + '.')).map((f) => f.split('.')[1]);
  const field = (name) => {
    const kids = nested(name);
    if (!kids.length) return `    ${name}?: unknown;`;
    return [`    ${name}: {`, ...kids.map((k) => `        ${k}?: unknown;`), '    };'].join('\n');
  };
  const obj = (name, keys) => [`export type ${name} = {`, ...keys.map((k) => `    ${k}: unknown;`), '};'].join('\n');
  return [
    obj('LiveProp', Object.keys(parity.client.livePropFields)),
    obj('ScrollProp', Object.keys(parity.client.scrollPropFields)),
    'export interface Page<SharedProps extends PageProps = PageProps> {',
    ...top.map(field),
    '    /** @internal */',
    '    rememberedState: Record<string, unknown>;',
    '}',
    '',
  ].join('\n');
}

function fakeDist({ headers = Object.keys(parity.client.headers) } = {}) {
  return headers.map((h, i) => `const h${i} = ${i % 2 ? `'${h.toLowerCase()}'` : `"${h}"`};`).join('\n');
}

function tarEntry(path, content) {
  const header = Buffer.alloc(512);
  header.write(path, 0);
  header.write('0000644\0', 100);
  header.write(Buffer.byteLength(content).toString(8).padStart(11, '0') + '\0', 124);
  header.write('ustar\0', 257);
  const body = Buffer.alloc(Math.ceil(Buffer.byteLength(content) / 512) * 512);
  body.write(content);
  return Buffer.concat([header, body]);
}

function fakeTgz(files) {
  const entries = Object.entries(files).map(([p, c]) => tarEntry(p, c));
  return gzipSync(Buffer.concat([...entries, Buffer.alloc(1024)]));
}

function fakeHeaderPhp(constants = parity.server.headerConstants) {
  return `<?php\nclass Header\n{\n${constants.map((c) => {
    const [name, value] = c.split('=');
    return `    public const ${name} = '${value}';`;
  }).join('\n')}\n}\n`;
}

function fakeResponseFactoryPhp(methods = parity.server.responseFactoryMethods) {
  return `<?php\nclass ResponseFactory\n{\n    public function __construct() {}\n${methods
    .map((m) => `    public function ${m}($x = null) {}`)
    .join('\n')}\n    protected function hidden() {}\n}\n`;
}

/** A fake of Packagist, npm, GitHub's tree API and raw.githubusercontent.com. */
function fakeHttp({
  laravel = parity.audited[LARAVEL],
  core = parity.audited[CORE],
  headers,
  pageFields,
  headerConstants,
  methods,
  srcFiles = parity.server.srcFiles,
} = {}) {
  const routes = {
    [`https://repo.packagist.org/p2/${LARAVEL}.json`]: {
      packages: { [LARAVEL]: [{ version: `v${laravel}` }, { version: 'v2.0.0' }, { version: 'v4.0.0-beta.1' }] },
    },
    'https://registry.npmjs.org/@inertiajs%2fcore/latest': { version: core },
    [`https://api.github.com/repos/inertiajs/inertia-laravel/git/trees/v${laravel}?recursive=1`]: {
      tree: [...srcFiles, 'tests/ResponseTest.php'].map((path) => ({ path, type: 'blob' })),
    },
    [`https://raw.githubusercontent.com/inertiajs/inertia-laravel/v${laravel}/src/Support/Header.php`]:
      fakeHeaderPhp(headerConstants),
    [`https://raw.githubusercontent.com/inertiajs/inertia-laravel/v${laravel}/src/ResponseFactory.php`]:
      fakeResponseFactoryPhp(methods),
    [`https://registry.npmjs.org/@inertiajs/core/-/core-${core}.tgz`]: fakeTgz({
      'package/dist/index.js': fakeDist({ headers }),
      'package/types/types.d.ts': fakeTypes({ pageFields }),
    }),
  };
  const get = (url) => {
    if (!(url in routes)) throw new Error(`unexpected fetch ${url}`);
    return routes[url];
  };
  return { json: async (u) => get(u), text: async (u) => get(u), buffer: async (u) => get(u) };
}

const TODO = `Audited against \`${LARAVEL}\` ${parity.audited[LARAVEL]} and \`${CORE}\` ${parity.audited[CORE]}.`;
const core = (opts = {}) => ({ version: parity.audited[CORE], distJs: fakeDist(opts), typesDts: fakeTypes(opts) });

// ---------------------------------------------------------------------------
// Extraction

test('extracts Page keys, nested keys, and skips @internal ones', () => {
  const dts = [
    'export interface Page<S extends P = P> {',
    '    component: string;',
    '    props: PageProps & S & {',
    '        errors: Errors;',
    '    };',
    '    onceProps?: Record<string, {',
    '        prop: keyof PageProps;',
    '        expiresAt?: number | null;',
    '    }>;',
    '    /** @internal */',
    '    rememberedState: Record<string, unknown>;',
    '    /** @internal */',
    '    queryMethodData?: {',
    '        secret: string;',
    '    };',
    '    url: string;',
    '}',
    'export type Other = { nope: string };',
  ].join('\n');
  assert.deepEqual(extractTypeKeys(dts, 'Page'), [
    'component',
    'props',
    'props.errors',
    'onceProps',
    'onceProps.prop',
    'onceProps.expiresAt',
    'url',
  ]);
});

test('normalises header case, so a lowercase read and a TitleCase send are one name', () => {
  const s = extractClientSurface({ distJs: `a("X-Inertia"); b['x-inertia']; c("x-inertia-location")`, typesDts: fakeTypes() });
  assert.deepEqual(s.headers, ['X-Inertia', 'X-Inertia-Location']);
});

test('reads the audited versions from TODO.md', () => {
  assert.deepEqual(todoVersions(TODO), { [LARAVEL]: parity.audited[LARAVEL], [CORE]: parity.audited[CORE] });
});

test('untar reads files out of an npm-style tarball', () => {
  const files = untar(fakeTgz({ 'package/a.txt': 'hello', 'package/b.txt': 'x'.repeat(700) }), ['package/b.txt']);
  assert.deepEqual(Object.keys(files), ['package/b.txt']);
  assert.equal(files['package/b.txt'].length, 700);
});

// ---------------------------------------------------------------------------
// local

test('local: in sync when the client uses only declared names', () => {
  const r = runLocal({ parity, core: core(), todo: TODO });
  assert.equal(r.drift, false, r.text);
  assert.match(r.text, /In sync\./);
});

test('local: drift on a header the adapter does not know', () => {
  const r = runLocal({ parity, core: core({ headers: [...Object.keys(parity.client.headers), 'X-Inertia-Brand-New'] }), todo: TODO });
  assert.equal(r.drift, true);
  assert.match(r.text, /\+ headers: X-Inertia-Brand-New/);
});

test('local: drift on a page field the adapter does not know', () => {
  const r = runLocal({ parity, core: core({ pageFields: [...Object.keys(parity.client.pageFields), 'brandNewProps'] }), todo: TODO });
  assert.equal(r.drift, true);
  assert.match(r.text, /\+ pageFields: brandNewProps/);
});

test('local: an older client lacking a declared name is fine', () => {
  const r = runLocal({ parity, core: core({ headers: ['X-Inertia'] }), todo: TODO });
  assert.equal(r.drift, false, r.text);
});

test('local: drift when TODO.md states other versions than parity.json', () => {
  const r = runLocal({ parity, core: core(), todo: TODO.replace(parity.audited[LARAVEL], '3.0.0') });
  assert.equal(r.drift, true);
  assert.match(r.text, /TODO\.md states inertiajs\/inertia-laravel 3\.0\.0/);
});

// ---------------------------------------------------------------------------
// upstream

test('upstream: in sync when the latest releases are the audited ones', async () => {
  const r = await runUpstream({ parity, http: fakeHttp() });
  assert.equal(r.drift, false, r.text);
  assert.match(r.text, /In sync\./);
});

test('upstream: drift on a new inertia-laravel release, with release-note links', async () => {
  const r = await runUpstream({ parity, http: fakeHttp({ laravel: '3.99.0' }) });
  assert.equal(r.drift, true);
  assert.match(r.text, /New release: inertiajs\/inertia-laravel 3\.7\.0 -> 3\.99\.0/);
  assert.match(r.text, /compare\/v3\.7\.0\.\.\.v3\.99\.0/);
});

test('upstream: drift on a new @inertiajs/core release', async () => {
  const r = await runUpstream({ parity, http: fakeHttp({ core: '3.99.0' }) });
  assert.equal(r.drift, true);
  assert.match(r.text, /New release: @inertiajs\/core 3\.11\.0 -> 3\.99\.0/);
});

test('upstream: drift on an older pinned audit, even with no surface change', async () => {
  const audited = { ...parity.audited, [LARAVEL]: '3.6.0' };
  const r = await runUpstream({ parity, http: fakeHttp(), audited });
  assert.equal(r.drift, true);
  assert.match(r.text, /3\.6\.0 -> 3\.7\.0/);
});

test('upstream: drift on new server API (method, header constant, file)', async () => {
  const r = await runUpstream({
    parity,
    http: fakeHttp({
      methods: [...parity.server.responseFactoryMethods, 'precognition'],
      headerConstants: [...parity.server.headerConstants, 'NEW=X-Inertia-New'],
      srcFiles: [...parity.server.srcFiles, 'src/NewProp.php'],
    }),
  });
  assert.equal(r.drift, true);
  assert.match(r.text, /\+ responseFactoryMethods: precognition/);
  assert.match(r.text, /\+ headerConstants: NEW=X-Inertia-New/);
  assert.match(r.text, /\+ srcFiles: src\/NewProp\.php/);
});

test('upstream: drift when the server drops something we recorded', async () => {
  const r = await runUpstream({ parity, http: fakeHttp({ methods: parity.server.responseFactoryMethods.filter((m) => m !== 'once') }) });
  assert.equal(r.drift, true);
  assert.match(r.text, /- responseFactoryMethods: once/);
});

test('upstream: drift on a new client header in the latest release', async () => {
  const r = await runUpstream({ parity, http: fakeHttp({ headers: [...Object.keys(parity.client.headers), 'X-Inertia-Brand-New'] }) });
  assert.equal(r.drift, true);
  assert.match(r.text, /\+ headers: X-Inertia-Brand-New/);
});

// ---------------------------------------------------------------------------
// CLI exit codes, with an unpacked fake @inertiajs/core

function fakeCoreDir(opts) {
  const dir = mkdtempSync(join(tmpdir(), 'parity-core-'));
  mkdirSync(join(dir, 'dist'));
  mkdirSync(join(dir, 'types'));
  writeFileSync(join(dir, 'package.json'), JSON.stringify({ version: parity.audited[CORE] }));
  writeFileSync(join(dir, 'dist/index.js'), fakeDist(opts));
  writeFileSync(join(dir, 'types/types.d.ts'), fakeTypes(opts));
  return dir;
}

function cli(args) {
  try {
    return { code: 0, out: execFileSync(process.execPath, [SCRIPT, ...args], { encoding: 'utf8', stdio: 'pipe' }) };
  } catch (err) {
    return { code: err.status, out: err.stdout + err.stderr };
  }
}

test('cli: local exits 0 in sync and 1 on drift', () => {
  const todo = join(mkdtempSync(join(tmpdir(), 'parity-todo-')), 'TODO.md');
  writeFileSync(todo, TODO);
  const ok = cli(['local', `--core-dir=${fakeCoreDir()}`, `--todo=${todo}`]);
  assert.equal(ok.code, 0, ok.out);
  const drift = cli(['local', `--core-dir=${fakeCoreDir({ headers: ['X-Inertia', 'X-Inertia-Brand-New'] })}`, `--todo=${todo}`]);
  assert.equal(drift.code, 1);
  assert.match(drift.out, /X-Inertia-Brand-New/);
});

test('finds @inertiajs/core next to @inertiajs/react in a pnpm layout, despite its import-only exports', async () => {
  const { findInstalledCore } = await import('./parity.mjs');
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'parity-pnpm-')));
  const store = join(root, 'node_modules/.pnpm/@inertiajs+react@3.11.0/node_modules/@inertiajs');
  mkdirSync(join(store, 'react'), { recursive: true });
  mkdirSync(join(store, 'core'), { recursive: true });
  writeFileSync(join(store, 'react/package.json'), '{}');
  writeFileSync(
    join(store, 'core/package.json'),
    JSON.stringify({ exports: { '.': { types: './types/index.d.ts', import: './dist/index.js' } } }),
  );
  mkdirSync(join(root, 'node_modules/@inertiajs'), { recursive: true });
  symlinkSync(join(store, 'react'), join(root, 'node_modules/@inertiajs/react'));
  assert.equal(findInstalledCore(root), join(store, 'core'));
});

test('cli: unknown command exits 2', () => {
  assert.equal(cli(['nope']).code, 2);
});
