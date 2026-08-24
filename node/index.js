// index.js — Node runtime entry point for the STANDALONE Instagram product.
//
// Run it directly:
//   cd node && npm install && node index.js
// or under a process manager:
//   pm2 start index.js --name instagram-node
//
// It exposes the Meta webhook ingest that drives the flow engine, starts the
// scheduler loop (scheduled posts + reels autopilot), and prunes finished flow
// sessions so memory does not grow unbounded. Everything real lives in the
// shared services (./services, ./controllers) so a flow behaves identically to
// the WaDesk extension build. Nothing boots on import — only the direct-
// execution guard at the bottom starts anything.

import './log-gate.js'; // global console gate — keep FIRST (see log-gate.js)
import express from 'express';
import http from 'node:http';
import crypto from 'node:crypto';

import config, { envFilesLoaded } from './config/config.js';
import instagramFlowController from './controllers/instagramFlowController.js';
import { pruneSessions } from './services/instagramFlowService.js';
import { startInstagramScheduler } from './services/instagram/igScheduler.js';

// ---- boot ---------------------------------------------------------------
// All settings come from config/config.js (WaDesk pattern) — nothing reads
// process.env inline here.
export function main() {
    const ENV_FILE = envFilesLoaded.length ? envFilesLoaded.join(' + ') : null;

    const PORT     = config.application.port;
    const APP_URL  = config.application.appUrl;   // Laravel base the worker calls back to
    const NODE_TOKEN = config.application.nodeToken;

    // Refuse to boot without the token: the Laravel endpoints fail closed on a
    // missing X-Node-Token, so booting anyway would produce a server that looks
    // healthy and silently drops every message.
    if (!NODE_TOKEN) {
        throw new Error(
            'NODE_TOKEN is not set. The Laravel callbacks (/api/instagram/flow-node, '
            + '/flow-log) reject requests without it, so the runtime would start and '
            + 'silently do nothing. Set NODE_TOKEN in node/.env to the same value as '
            + 'admin -> Instagram settings -> Node token.'
        );
    }

    const app = express();

    // Meta webhook bodies are JSON and can carry media metadata; the default
    // 100kb limit truncates larger payloads into a parse error that reads like
    // a bad signature.
    app.use(express.json({ limit: '5mb' }));
    app.use(express.urlencoded({ extended: true }));

    // ---- request log --------------------------------------------------------
    // One line per request (gated by log-gate — visible unless INSTAFLOW_LOGS=off),
    // so you can watch Laravel's hand-offs land, like WaDesk's node logs.
    app.use((req, _res, next) => {
        console.log(`[instagram-node] ${req.method} ${req.originalUrl}`);
        next();
    });

    // ---- auth ---------------------------------------------------------------
    // Hash both sides before comparing: timingSafeEqual throws on a length
    // mismatch, and that throw is itself an oracle. Hashing makes both operands
    // a fixed 32 bytes, so the comparison is constant time over the whole input.
    const sha256 = (v) => crypto.createHash('sha256').update(String(v ?? '')).digest();
    const EXPECTED_DIGEST = sha256(NODE_TOKEN);

    const requireNodeToken = (req, res, next) => {
        if (!crypto.timingSafeEqual(sha256(req.headers['x-node-token'] || ''), EXPECTED_DIGEST)) {
            return res.status(401).json({ ok: false, error: 'unauthorized' });
        }
        return next();
    };

    // ---- root ---------------------------------------------------------------
    // A friendly status page so opening the node URL in a browser shows the
    // worker is alive (WaDesk does the same), instead of a bare 404 that reads
    // like "nothing is running".
    app.get('/', (req, res) => {
        res.json({
            message: 'InfiniDM Node running fine',
            status: 200,
            service: 'infinidm-node',
            uptime: Math.round(process.uptime()),
            laravel: APP_URL,
            port: PORT,
            endpoints: ['/health', '/api/instagram-flow/inbound', '/api/instagram-flow/health'],
        });
    });

    // ---- health -------------------------------------------------------------
    // Dependency-free AND unauthenticated so a load balancer can probe it even
    // while the scheduler or Laravel is down. Detailed stats like WaDesk's.
    app.get('/health', (req, res) => {
        res.json({
            ok: true,
            status: 'healthy',
            service: 'infinidm-node',
            mode: 'standalone',
            uptime: Math.round(process.uptime()),
            timestamp: new Date().toISOString(),
            laravel: APP_URL,
            port: PORT,
            memoryUsage: process.memoryUsage(),
        });
    });

    // ---- flow engine --------------------------------------------------------
    // Laravel forwards verified Meta webhook events here; signature checking has
    // already happened there. The /api/instagram-flow/* pair is the contract:
    // IgFlowNodeBridge.php posts to exactly that path.
    app.post('/api/instagram-flow/inbound', requireNodeToken, instagramFlowController.instagramInbound);
    app.get('/api/instagram-flow/health', requireNodeToken, instagramFlowController.instagramFlowHealth);

    // ---- not found ----------------------------------------------------------
    app.use((req, res) => {
        res.status(404).json({ ok: false, error: 'not found', path: req.path });
    });

    // ---- error handling -----------------------------------------------------
    app.use((err, req, res, _next) => {
        const status = err?.status && err.status >= 400 && err.status < 600 ? err.status : 500;
        if (status >= 500) console.error('[instagram-node] unhandled error on ' + req.path, err);
        res.status(status).json({ ok: false, error: err?.message || 'internal error' });
    });

    const server = http.createServer(app);

    server.listen(PORT, () => {
        console.log(`[instagram-node] listening on :${PORT}`);
        console.log(`[instagram-node] Laravel at ${APP_URL}`);
        if (ENV_FILE) console.log(`[instagram-node] env loaded from ${ENV_FILE}`);

        // Scheduled posts + reels autopilot. Owns its own timing; Laravel only
        // hands it work through /api/instagram/jobs/due.
        try {
            const started = startInstagramScheduler(APP_URL);
            console.log(started
                ? '[instagram-node] scheduler started'
                : '[instagram-node] scheduler did NOT start — scheduled posts and reels autopilot are inactive');
        } catch (e) {
            console.error('[instagram-node] scheduler failed to start:', e?.message || e);
        }
    });

    // Flow sessions live in memory between a question and its answer. Prune so a
    // long-running process does not accumulate every abandoned conversation.
    const PRUNE_EVERY_MS = 60_000;
    const pruneTimer = setInterval(() => {
        try {
            pruneSessions();
        } catch (e) {
            console.error('[instagram-node] session prune failed:', e?.message || e);
        }
    }, PRUNE_EVERY_MS);
    pruneTimer.unref();

    // ---- shutdown -----------------------------------------------------------
    let shuttingDown = false;
    const GRACE_MS = 10_000;

    for (const sig of ['SIGINT', 'SIGTERM']) {
        process.on(sig, () => {
            if (shuttingDown) {
                console.warn(`[instagram-node] ${sig} again — exiting now`);
                return process.exit(1);
            }
            shuttingDown = true;
            console.log(`[instagram-node] ${sig} — shutting down`);

            clearInterval(pruneTimer);
            server.close(() => {
                console.log('[instagram-node] closed cleanly');
                process.exit(0);
            });
            server.closeIdleConnections?.();

            setTimeout(() => {
                console.error(`[instagram-node] still busy after ${GRACE_MS}ms — forcing exit`);
                process.exit(1);
            }, GRACE_MS).unref();
        });
    }

    process.on('unhandledRejection', (r) => console.error('[instagram-node] unhandled rejection:', r));

    return server;
}

// ---- boot ----------------------------------------------------------------
// Call main() directly — this file is always the process entry point (node
// index.js / npm start / pm2 start index.js). We deliberately do NOT gate on
// `process.argv[1] === this file`: pm2 fork mode replaces argv[1] with its own
// wrapper, so that guard would silently skip main() under pm2 — the process
// would show "online" but never bind a port (exactly the empty-logs symptom).
try {
    main();
} catch (e) {
    console.error('[instagram-node] FATAL: ' + (e?.message || e));
    process.exit(1);
}
