// config/config.js — central runtime config for the Instaflow Node worker.
//
// Same pattern as WaDesk's node/config/config.js: load the env, then export a
// single `config` object so nothing else reads process.env inline. Instaflow
// reads node/.env first (the worker's own config), then the Laravel root .env
// as a fallback. A real shell/pm2 env var always wins over both. Kept
// dependency-free (no dotenv) so it runs against the shipped node_modules.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));

function loadEnvFiles() {
    const candidates = [
        path.resolve(HERE, '../.env'),      // node/.env — the worker's own config
        path.resolve(HERE, '../../.env'),   // Laravel root .env — fallback
    ];
    const loaded = [];
    for (const file of candidates) {
        let raw;
        try { raw = fs.readFileSync(file, 'utf8'); } catch (_) { continue; }
        for (const line of raw.split(/\r?\n/)) {
            const m = /^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/.exec(line);
            if (!m) continue;                                   // blank or # comment
            if (process.env[m[1]] !== undefined) continue;      // already set wins
            process.env[m[1]] = m[2].trim().replace(/^(["'])([\s\S]*)\1$/, '$2');
        }
        loaded.push(file);
    }
    return loaded;
}

// Populate process.env from the files on import, and remember which loaded.
export const envFilesLoaded = loadEnvFiles();

// Accepts the SAME keys as WaDesk's node/.env so this is configured identically:
//   PORT             — the port the worker listens on   (WaDesk: PORT)
//   APP_DOMAIN_NAME  — the Laravel site URL              (WaDesk: APP_DOMAIN_NAME)
//   NODE_WEBHOOK_TOKEN — the shared X-Node-Token secret  (WaDesk: NODE_WEBHOOK_TOKEN)
// Instaflow's own aliases (NODE_PORT / APP_URL / NODE_TOKEN) are also accepted.
const config = {
    application: {
        // Port the worker listens on (health + flow webhook).
        port: Number(process.env.PORT || process.env.NODE_PORT || 3001),

        // Laravel base URL the worker calls back to. This is your WEBSITE url —
        // NOT the node port. In WaDesk this is APP_DOMAIN_NAME.
        appUrl: (process.env.APP_DOMAIN_NAME || process.env.APP_URL || process.env.LARAVEL_URL || 'http://127.0.0.1:8000').replace(/\/+$/, ''),

        // The node's own base URL (self). WaDesk calls this DOMAIN_NAME. Optional.
        domainName: (process.env.DOMAIN_NAME || '').replace(/\/+$/, ''),

        // Shared secret (X-Node-Token). WaDesk: NODE_WEBHOOK_TOKEN.
        nodeToken: process.env.NODE_WEBHOOK_TOKEN || process.env.NODE_TOKEN || '',
    },
};

// Publish under the canonical names the shared services read (they read
// process.env directly), so a value set only under an alias still reaches them.
process.env.APP_URL = config.application.appUrl;
if (config.application.nodeToken) process.env.NODE_WEBHOOK_TOKEN = config.application.nodeToken;

export default config;
