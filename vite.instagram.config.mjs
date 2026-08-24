import { defineConfig } from 'vite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

/**
 * Build the Instagram extension bundles emitted to public/extensions/instagram/.
 *
 * Non-hashed filenames (the blade names them directly, no manifest). Cache-bust
 * via the layout's ?v= query. Vendor deps bundle into the entry that imports
 * them. Run:  npx vite build --config vite.instagram.config.mjs
 *
 * SCOPED BUILD: only the entries listed in ONLY_ENTRIES are (re)built.
 * emptyOutDir is false, so every other already-shipped bundle is left exactly
 * as-is. This exists because the local resources/js/charts snapshot has drifted
 * behind some shipped bundles — rebuilding an entry we didn't intend to touch
 * would regress it. Add a name here only when its source is known-current.
 */
const ONLY_ENTRIES = ['instagram-flow-builder'];

const chartsDir = path.join(here, 'resources/js/charts');
const wrapperDir = path.join(here, '.build-entries');

fs.rmSync(wrapperDir, { recursive: true, force: true });
fs.mkdirSync(wrapperDir, { recursive: true });

const input = {};

for (const file of fs.readdirSync(chartsDir).filter((f) => f.endsWith('.js'))) {
    const name = file.replace(/\.js$/, '');
    if (!ONLY_ENTRIES.includes(name)) continue;

    // Each chart module exports `default function init()`; standalone <script>
    // tags don't call it, so wrap the entry in a DOM-ready shim.
    const wrapper = path.join(wrapperDir, `${name}.js`);
    fs.writeFileSync(
        wrapper,
        `import init from ${JSON.stringify(path.join(chartsDir, file).replace(/\\/g, '/'))};\n` +
            `const run = () => { try { init(); } catch (e) { console.error('[instagram] ${name} failed', e); } };\n` +
            `if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);\n` +
            `else run();\n`,
        'utf8'
    );
    input[name] = wrapper;
}

export default defineConfig({
    root: here,
    // publicDir MUST be false — outDir lives inside public/, and leaving it on
    // makes the build copy public/ into itself recursively.
    publicDir: false,
    build: {
        outDir: path.join(here, 'public/extensions/instagram'),
        emptyOutDir: false,
        manifest: false,
        rollupOptions: {
            input,
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
                inlineDynamicImports: false,
            },
        },
    },
});
