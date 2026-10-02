// Builds every JS/CSS bundle of the plugin with esbuild.
//   node tools/build.mjs          production build
//   node tools/build.mjs --watch  rebuild on change (unminified, with sourcemaps)
import * as esbuild from 'esbuild';
import { readdir, rm, mkdir, copyFile, access } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { dirname, resolve, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

// Runs from the repository (tools/build.mjs, plugin in ./uncoder) and from the released plugin
// folder (build.mjs next to src/, after `npm install` there).
const here = dirname(fileURLToPath(import.meta.url));
const inPlugin = existsSync(resolve(here, 'src/editor'));
const root = inPlugin ? here : resolve(here, '..');
const plugin = inPlugin ? here : resolve(root, 'uncoder');
const src = resolve(plugin, 'src');
const out = resolve(plugin, 'assets/build');
const watch = process.argv.includes('--watch');
const dev = watch || process.argv.includes('--dev');

const list = async (dir, ext) =>
  (await readdir(dir).catch(() => [])).filter((f) => f.endsWith(ext)).map((f) => resolve(dir, f));

const common = {
  bundle: true,
  minify: !dev,
  sourcemap: dev ? 'linked' : false,
  target: ['es2020', 'chrome100', 'firefox100', 'safari15'],
  legalComments: 'none',
  logLevel: 'info',
  define: { 'process.env.NODE_ENV': dev ? '"development"' : '"production"' },
  alias: {
    '@shared': resolve(src, 'shared'),
    '@editor': resolve(src, 'editor'),
  },
  // Font files are shipped in assets/fonts and referenced relatively from the built CSS.
  external: ['*.woff2'],
};

if (!watch) await rm(out, { recursive: true, force: true });
await mkdir(out, { recursive: true });

const builds = [
  // Front-end runtime + one file per module (loaded only when a widget needs it).
  {
    ...common,
    entryPoints: [resolve(src, 'frontend/runtime.ts')],
    outfile: resolve(out, 'frontend/runtime.js'),
    format: 'iife',
  },
  {
    ...common,
    entryPoints: await list(resolve(src, 'frontend/modules'), '.ts'),
    outdir: resolve(out, 'frontend/modules'),
    format: 'iife',
  },
  {
    ...common,
    entryPoints: [resolve(src, 'frontend/css/frontend.css')],
    outfile: resolve(out, 'frontend/frontend.css'),
    loader: { '.svg': 'dataurl' },
  },
  {
    ...common,
    entryPoints: await list(resolve(src, 'frontend/css/widgets'), '.css'),
    outdir: resolve(out, 'frontend/widgets'),
  },
  // Editor app (React bundled, isolated full-screen page) and canvas overlay styles.
  {
    ...common,
    entryPoints: { editor: resolve(src, 'editor/main.tsx') },
    outdir: resolve(out, 'editor'),
    format: 'iife',
    jsx: 'automatic',
    loader: { '.svg': 'text', '.woff2': 'file' },
  },
  {
    ...common,
    entryPoints: [resolve(src, 'editor/canvas/canvas.css')],
    outfile: resolve(out, 'editor/canvas.css'),
  },
  // WordPress's own screens: the post editors' Edit with Uncoder button and built-with panel (block + classic),
  // and the front-end admin bar's Edit with Uncoder menu.
  {
    ...common,
    entryPoints: { 'post-editor': resolve(src, 'wp/post-editor.ts'), 'admin-bar': resolve(src, 'wp/admin-bar.ts'), 'nav-menus': resolve(src, 'wp/nav-menus.ts') },
    outdir: resolve(out, 'wp'),
    format: 'iife',
  },
  // Admin app.
  {
    ...common,
    entryPoints: { admin: resolve(src, 'admin/main.tsx') },
    outdir: resolve(out, 'admin'),
    format: 'iife',
    jsx: 'automatic',
    loader: { '.svg': 'text' },
  },
];

// Third-party player files shipped as-is (outside assets/build, which is wiped on every build).
// lottie-web (MIT): the "light" build has only the SVG renderer and no expression support (no eval).
async function vendor() {
  const files = [
    ['node_modules/lottie-web/build/player/lottie_light.min.js', 'assets/vendor/lottie/lottie_light.min.js'],
    ['node_modules/lottie-web/LICENSE.md', 'assets/vendor/lottie/LICENSE.md'],
  ];
  for (const [from, to] of files) {
    const src = resolve(root, from);
    if (!(await access(src).then(() => true, () => false))) continue;
    await mkdir(dirname(resolve(plugin, to)), { recursive: true });
    await copyFile(src, resolve(plugin, to));
  }
}

const existing = [];
for (const b of builds) {
  const entries = Array.isArray(b.entryPoints) ? b.entryPoints : Object.values(b.entryPoints);
  if (entries.length) existing.push(b);
}

if (watch) {
  for (const b of existing) {
    const ctx = await esbuild.context(b);
    await ctx.watch();
  }
  console.log('watching…');
} else {
  const started = Date.now();
  await Promise.all(existing.map((b) => esbuild.build(b)));
  console.log(`built ${existing.length} bundles in ${Date.now() - started}ms`);
  await vendor();
  console.log('modules:', (await list(resolve(out, 'frontend/modules'), '.js')).map((f) => basename(f, '.js')).join(', '));
}
