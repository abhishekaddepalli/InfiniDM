// =============================================================
// Instagram scheduler (Node) — Node owns ALL Instagram timing.
//
// Every tick it pulls due jobs from Laravel (GET /api/instagram/jobs/due),
// claims each atomically, and fires it via igGraphClient (the official Graph
// API, in Node), then reports the result. Covers: scheduled posts/stories/
// reels/carousels, DM broadcasts (cursor drain), and the reels-autoposter
// (scrape on interval → publish from the queue). Mirrors Reels-AutoPilot's
// app.py per-task timers + our WA scheduleService pattern. State of record
// lives in Laravel — a Node restart simply re-pulls.
// =============================================================
import axios from "axios";
import IgGraphClient from "./igGraphClient.js";
import { scrapeSources } from "./reposterScraper.js";

const TICK_MS = 30000;             // pull cadence
const BROADCAST_BATCH = 20;        // DMs per broadcast pass
const repostTimers = {};           // account_id → { nextScrape, nextPost, nextClean }
const TOKEN_REFRESH_MS = 6 * 60 * 60 * 1000;   // re-exchange nearing-expiry tokens every 6h
let running = false;
let nextTokenRefresh = 0;           // 0 → runs on the first tick after boot

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const headers = () => ({ "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "", Accept: "application/json" });

async function claim(appDomain, kind, id, nodeJobId = "") {
  try {
    const r = await axios.post(`${appDomain}/api/instagram/jobs/claim`,
      { kind, id, node_job_id: nodeJobId }, { headers: headers(), timeout: 10000 });
    return !!(r.data && r.data.won);
  } catch (e) {
    console.warn(`[IG-SCHED] claim ${kind}#${id} failed: ${e?.message}`);
    return false;
  }
}

async function report(appDomain, payload) {
  try {
    await axios.post(`${appDomain}/api/instagram/jobs/result`, payload, { headers: headers(), timeout: 15000 });
  } catch (e) {
    console.warn(`[IG-SCHED] result ${payload.kind}#${payload.id} failed: ${e?.message}`);
  }
}

// ---- scheduled post / story / reel / carousel --------------------------------
async function fireScheduled(appDomain, job) {
  if (!(await claim(appDomain, "scheduled", job.id, `igsched_${Date.now()}`))) return;
  const g = new IgGraphClient(job.auth);
  const cap = job.caption || "";
  try {
    let mediaId;
    switch (job.media_type) {
      case "reels":
        mediaId = await g.publishReel(job.video_url, cap);
        break;
      case "story":
        mediaId = await g.publishStory(job.video_url || job.image_url, !!job.video_url);
        break;
      case "carousel": {
        const urls = Array.isArray(job.media_urls) ? job.media_urls : [];
        const items = urls.map((u) => ({ url: u, video: /\.mp4(\?|$)/i.test(String(u)) }));
        mediaId = await g.publishCarousel(items, cap);
        break;
      }
      default:
        mediaId = await g.publishImage(job.image_url, cap);
    }
    console.log(`[IG-SCHED] published scheduled #${job.id} (${job.media_type}) → ${mediaId}`);
    await report(appDomain, { kind: "scheduled", id: job.id, status: "published", media_id: mediaId });
  } catch (e) {
    console.warn(`[IG-SCHED] scheduled #${job.id} failed: ${e?.message}`);
    await report(appDomain, { kind: "scheduled", id: job.id, status: "failed", error: String(e?.message || e) });
  }
}

// ---- DM broadcast (drain a batch, resume next tick from cursor) ---------------
async function drainBroadcast(appDomain, job) {
  if (!(await claim(appDomain, "broadcast", job.id))) return;
  const g = new IgGraphClient(job.auth);
  const recips = Array.isArray(job.recipients) ? job.recipients : [];
  let cursor = job.cursor || 0;
  let sent = 0, failed = 0, lastError = "";
  const results = []; // per-recipient outcome for the Laravel ledger
  const end = Math.min(recips.length, cursor + BROADCAST_BATCH);
  for (let i = cursor; i < end; i++) {
    try {
      const resp = await g.sendDm(recips[i], { text: job.body });
      sent++;
      results.push({ igsid: recips[i], ok: true, mid: (resp && (resp.message_id || resp.mid)) || "" });
    } catch (e) {
      failed++;
      lastError = String(e?.message || e);
      results.push({ igsid: recips[i], ok: false, error: String(e?.message || e).slice(0, 300) });
    }
    cursor = i + 1;
    await sleep(800 + Math.floor(Math.random() * 700)); // pacing / anti-spam
  }
  const done = cursor >= recips.length;
  // sent/failed are DELTAS — the controller increments; results stamps the ledger.
  await report(appDomain, {
    kind: "broadcast", id: job.id, cursor, sent, failed, results,
    bstatus: done ? "done" : "running", error: lastError,
  });
}

// ---- reposter: scrape on interval + publish from the queue --------------------
async function tickReposter(appDomain, cfg) {
  const id = cfg.account_id;
  const t = (repostTimers[id] ||= { nextScrape: 0, nextPost: 0, nextClean: 0 });
  const now = Date.now();

  // 1. scrape new clips into the queue (best-effort, fire-and-forget)
  if (now >= t.nextScrape) {
    t.nextScrape = now + Math.max(10, cfg.scraper_interval_min) * 60000;
    scrapeSources(appDomain, cfg).catch((e) => console.warn(`[IG-REPOST] scrape acct#${id}: ${e?.message}`));
  }

  // 2. publish the next queued clip — interval-gated + under the daily cap
  if (now >= t.nextPost && cfg.next_queued && cfg.posted_today < cfg.daily_cap) {
    t.nextPost = now + Math.max(1, cfg.posting_interval_min) * 60000 + Math.floor(Math.random() * 20000);
    const item = cfg.next_queued;
    if (item.public_url && (await claim(appDomain, "repost", item.id))) {
      const g = new IgGraphClient(cfg.auth);
      const cap = (cfg.hashtags && cfg.hashtags.trim()) || item.caption || "";
      try {
        const mediaId = await g.publishReel(item.public_url, cap);
        console.log(`[IG-REPOST] posted reel acct#${id} item#${item.id} → ${mediaId}`);
        await report(appDomain, { kind: "repost", id: item.id, status: "posted", media_id: mediaId });
        if (cfg.post_to_story) {
          try { await g.publishStory(item.public_url, true); } catch (_) { /* story is best-effort */ }
        }
      } catch (e) {
        console.warn(`[IG-REPOST] post acct#${id} item#${item.id} failed: ${e?.message}`);
        await report(appDomain, { kind: "repost", id: item.id, status: "failed", error: String(e?.message || e) });
      }
    }
  }

  // 3. cleanup hosted files for posted clips (every 30 min)
  if (now >= t.nextClean) {
    t.nextClean = now + 30 * 60000;
    axios.post(`${appDomain}/api/instagram/reposter/cleanup`,
      { account_id: id, older_than_min: cfg.remove_after_min },
      { headers: headers(), timeout: 15000 }).catch(() => {});
  }
}

async function tick(appDomain) {
  if (running) return; // never overlap ticks
  running = true;
  try {
    const r = await axios.get(`${appDomain}/api/instagram/jobs/due`, { headers: headers(), timeout: 20000 });
    const d = r.data || {};
    for (const j of d.scheduled || []) await fireScheduled(appDomain, j);
    for (const b of d.broadcasts || []) await drainBroadcast(appDomain, b);
    for (const c of d.reposter || []) await tickReposter(appDomain, c);

    // Long-lived token keep-alive — Node owns the timing; Laravel does the
    // actual exchange (tokens are encrypted at rest + FB extend needs the app
    // secret). Every 6h, ask Laravel to refresh any nearing-expiry tokens.
    if (Date.now() >= nextTokenRefresh) {
      nextTokenRefresh = Date.now() + TOKEN_REFRESH_MS;
      try {
        const tr = await axios.post(`${appDomain}/api/instagram/refresh-tokens`, {},
          { headers: headers(), timeout: 30000 });
        const { refreshed = 0, failed = 0 } = tr.data || {};
        if (refreshed || failed) console.log(`[IG-SCHED] token refresh: ${refreshed} ok, ${failed} failed`);
      } catch (e) {
        console.warn(`[IG-SCHED] token refresh failed: ${e?.response?.status || ""} ${e?.message}`);
      }
    }
  } catch (e) {
    console.warn(`[IG-SCHED] tick failed: ${e?.response?.status || ""} ${e?.message}`);
  } finally {
    running = false;
  }
}

/**
 * Start the scheduler. Call once at Node boot with the Laravel app domain.
 *
 * Returns whether it actually started. A missing appDomain is a config
 * mistake, not an exception, so callers cannot detect it with try/catch —
 * standalone's boot log claimed "scheduler started" for exactly that reason.
 * Core ignores the return value.
 */
export function startInstagramScheduler(appDomain) {
  if (!appDomain) {
    console.warn("[IG-SCHED] no appDomain — Instagram scheduler NOT started");
    return false;
  }
  console.log(`[IG-SCHED] started — pulling Instagram jobs every ${TICK_MS / 1000}s`);
  setTimeout(() => tick(appDomain), 8000);   // initial run after boot settles
  setInterval(() => tick(appDomain), TICK_MS);
  return true;
}

export default { startInstagramScheduler };
