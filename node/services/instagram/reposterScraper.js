// =============================================================
// Reels scraper (Node, pure-JS — NO external binaries to install).
//
// Sources (no IG login):
//   • YouTube Shorts — YouTube Data API (axios) to list a channel's #shorts,
//     then @distube/ytdl-core (optional npm dep) to download a progressive
//     mp4 (audio+video combined → no ffmpeg needed).
//   • Instagram public reels — best-effort no-login fetch of a public reel
//     URL's embedded video (axios). May be blocked by IG without cookies;
//     logged + skipped gracefully when so.
//
// Each new clip is uploaded (multipart) to Laravel /reposter/enqueue, which
// hosts it publicly + dedups by source_id. We also keep a per-process seen
// set so we don't re-download clips already enqueued this run.
// =============================================================
import axios from "axios";
import fs from "fs";
import os from "os";
import path from "path";
import FormData from "form-data";

// Optional pure-Node YouTube downloader — degrade gracefully if not installed.
// Only the @distube fork: upstream ytdl-core is unmaintained and is declared in
// neither manifest, so importing it could only ever resolve via a stray hoist.
let ytdl = null;
try { ytdl = (await import("@distube/ytdl-core")).default; } catch (_) { ytdl = null; }

const TMP = path.join(os.tmpdir(), "ig-reposter");
const seen = new Set(); // `${accountId}:${sourceId}` already enqueued this process
const nodeHeaders = () => ({ "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" });

function ensureTmp() { try { fs.mkdirSync(TMP, { recursive: true }); } catch (_) {} }
function rm(p) { try { if (p && fs.existsSync(p)) fs.unlinkSync(p); } catch (_) {} }

// ---- enqueue a downloaded clip to Laravel (multipart) ------------------------
async function enqueue(appDomain, { accountId, source, sourceId, handle, caption, filePath }) {
  const key = `${accountId}:${sourceId}`;
  if (seen.has(key)) return false;
  const form = new FormData();
  form.append("account_id", String(accountId));
  form.append("source", source);
  form.append("source_id", String(sourceId));
  if (handle) form.append("source_handle", String(handle).slice(0, 190));
  if (caption) form.append("caption", String(caption).slice(0, 2000));
  form.append("video", fs.createReadStream(filePath), { filename: path.basename(filePath), contentType: "video/mp4" });
  const r = await axios.post(`${appDomain}/api/instagram/reposter/enqueue`, form, {
    headers: { ...form.getHeaders(), ...nodeHeaders() },
    maxBodyLength: Infinity, maxContentLength: Infinity, timeout: 180000,
  });
  seen.add(key);
  return !!(r.data && r.data.ok);
}

async function downloadFile(url, outPath) {
  const w = fs.createWriteStream(outPath);
  const resp = await axios.get(url, { responseType: "stream", timeout: 120000, maxContentLength: Infinity });
  await new Promise((res, rej) => { resp.data.pipe(w); w.on("finish", res); w.on("error", rej); });
  return outPath;
}

// ---- YouTube ----------------------------------------------------------------
async function resolveChannelId(channelUrlOrHandle, apiKey) {
  const m = String(channelUrlOrHandle).match(/youtube\.com\/channel\/([^/?&]+)/i);
  if (m) return m[1];
  // @handle (with or without the youtube.com/@ prefix)
  const h = String(channelUrlOrHandle).replace(/^.*@/, "").replace(/[/?&].*$/, "");
  const r = await axios.get("https://www.googleapis.com/youtube/v3/channels", {
    params: { part: "contentDetails", forHandle: h, key: apiKey }, timeout: 15000,
  });
  const item = r.data && r.data.items && r.data.items[0];
  if (!item) throw new Error(`channel not found for "${channelUrlOrHandle}"`);
  return item.id;
}

async function listYouTubeShorts(channelUrlOrHandle, apiKey, limit) {
  const channelId = await resolveChannelId(channelUrlOrHandle, apiKey);
  const ch = await axios.get("https://www.googleapis.com/youtube/v3/channels", {
    params: { part: "contentDetails", id: channelId, key: apiKey }, timeout: 15000,
  });
  const uploads = ch.data?.items?.[0]?.contentDetails?.relatedPlaylists?.uploads;
  if (!uploads) throw new Error("no uploads playlist");
  const pl = await axios.get("https://www.googleapis.com/youtube/v3/playlistItems", {
    params: { part: "snippet", maxResults: 50, playlistId: uploads, key: apiKey }, timeout: 15000,
  });
  const out = [];
  for (const it of pl.data?.items || []) {
    const sn = it.snippet || {};
    const title = sn.title || "";
    const desc = sn.description || "";
    if (/#shorts/i.test(title) || /#shorts/i.test(desc)) {
      out.push({ id: sn.resourceId?.videoId, title, url: `https://www.youtube.com/watch?v=${sn.resourceId?.videoId}` });
    }
    if (out.length >= limit) break;
  }
  return out.filter((s) => s.id);
}

async function downloadYouTube(videoId, outPath) {
  if (!ytdl) throw new Error("@distube/ytdl-core not installed");
  const url = `https://www.youtube.com/watch?v=${videoId}`;
  await new Promise((res, rej) => {
    const w = fs.createWriteStream(outPath);
    // progressive = audio+video in one mp4 → no ffmpeg muxing needed.
    const stream = ytdl(url, { filter: (f) => f.container === "mp4" && f.hasVideo && f.hasAudio, quality: "highest" });
    stream.pipe(w);
    w.on("finish", res);
    stream.on("error", rej);
    w.on("error", rej);
  });
  return outPath;
}

// ---- Instagram (public, best-effort, no login) -------------------------------
// Accepts a public reel URL OR a bare handle. For a handle we can't reliably
// enumerate reels without auth in 2026, so we only resolve direct reel URLs;
// a handle entry is logged + skipped (use YouTube for bulk, or paste reel URLs).
async function listIgReels(entry, _limit) {
  const url = String(entry).trim();
  if (!/instagram\.com\/(reel|p|tv)\//i.test(url)) {
    console.warn(`[IG-REPOST] IG source "${entry}" is not a public reel URL — handle enumeration needs login; skipping`);
    return [];
  }
  const code = (url.match(/instagram\.com\/(?:reel|p|tv)\/([^/?#]+)/i) || [])[1];
  if (!code) return [];
  // Fetch the public page and pull the embedded video URL (best-effort).
  const r = await axios.get(url.split("?")[0], {
    timeout: 20000,
    headers: { "User-Agent": "Mozilla/5.0", "Accept-Language": "en-US,en;q=0.9" },
    validateStatus: () => true,
  });
  const html = typeof r.data === "string" ? r.data : "";
  let video = (html.match(/"video_url":"([^"]+)"/) || [])[1]
    || (html.match(/property="og:video"\s+content="([^"]+)"/) || [])[1]
    || (html.match(/property="og:video:secure_url"\s+content="([^"]+)"/) || [])[1];
  if (!video) { console.warn(`[IG-REPOST] no public video_url for ${url} (likely needs login)`); return []; }
  video = video.replace(/\\u0026/g, "&").replace(/\\\//g, "/");
  const cap = (html.match(/"edge_media_to_caption".*?"text":"([^"]+)"/) || [])[1] || "";
  return [{ id: code, url: video, caption: cap }];
}

// ---- orchestrator ------------------------------------------------------------
export async function scrapeSources(appDomain, cfg) {
  ensureTmp();
  const limit = Math.max(1, cfg.fetch_limit || 10);

  // YouTube
  if (cfg.youtube_enabled && cfg.youtube_api_key) {
    if (!ytdl) {
      console.warn("[IG-REPOST] YouTube enabled but @distube/ytdl-core missing — `npm i @distube/ytdl-core`");
    } else {
      for (const ch of cfg.source_yt_channels || []) {
        let shorts = [];
        try { shorts = await listYouTubeShorts(ch, cfg.youtube_api_key, limit); }
        catch (e) { console.warn(`[IG-REPOST] yt list "${ch}": ${e?.message}`); continue; }
        for (const s of shorts) {
          if (seen.has(`${cfg.account_id}:${s.id}`)) continue;
          const out = path.join(TMP, `yt_${s.id}.mp4`);
          try {
            await downloadYouTube(s.id, out);
            await enqueue(appDomain, { accountId: cfg.account_id, source: "youtube", sourceId: s.id, handle: ch, caption: s.title, filePath: out });
          } catch (e) { console.warn(`[IG-REPOST] yt ${s.id}: ${e?.message}`); }
          finally { rm(out); }
        }
      }
    }
  }

  // Instagram (public reel URLs)
  for (const entry of cfg.source_ig_accounts || []) {
    let reels = [];
    try { reels = await listIgReels(entry, limit); }
    catch (e) { console.warn(`[IG-REPOST] ig "${entry}": ${e?.message}`); continue; }
    for (const r of reels) {
      if (seen.has(`${cfg.account_id}:${r.id}`)) continue;
      const out = path.join(TMP, `ig_${r.id}.mp4`);
      try {
        await downloadFile(r.url, out);
        await enqueue(appDomain, { accountId: cfg.account_id, source: "ig", sourceId: r.id, handle: entry, caption: r.caption, filePath: out });
      } catch (e) { console.warn(`[IG-REPOST] ig dl ${r.id}: ${e?.message}`); }
      finally { rm(out); }
    }
  }
}

export default { scrapeSources };
