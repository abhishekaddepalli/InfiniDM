// =============================================================
// Instagram Graph API client (Node) — the OFFICIAL posting path.
//
// Node owns Instagram scheduling AND posting: the scheduler fires a due
// job at the right time and this client makes the real Graph calls. Built
// against the web-verified spec (Graph v23–v25, 2026):
//   D:\Vault\kapil\Wasnap - IG Graph API verified spec.md
//
// Universal 2-step publish: POST /{ig}/media (container) → [poll status for
// video/reel] → POST /{ig}/media_publish (creation_id). Media URLs must be
// PUBLIC HTTPS — Meta cURLs them server-side (Laravel hosts them).
//
// Construct per account with the base (host+version) + token + ig user id
// that Laravel hands over (host = graph.facebook.com for FB-Login, or
// graph.instagram.com for IG-Login; same derivation as PHP InstagramService).
// =============================================================
import axios from "axios";

export class IgGraphClient {
  /** @param {{ base:string, token:string, igUserId?:string, ig?:string }} cfg */
  constructor({ base, token, igUserId, ig }) {
    this.base = String(base || "").replace(/\/+$/, ""); // e.g. https://graph.facebook.com/v23.0
    this.token = String(token || "");
    // Laravel's accountAuth() sends the id under `ig`; accept either key so the
    // send path is never `{base}//messages` (empty id → Graph error 100).
    this.ig = String(igUserId || ig || "");
    this.lastError = null;
  }

  isReady() {
    return !!(this.base && this.token && this.ig);
  }

  // ---- low-level ----------------------------------------------------------
  async _post(path, body) {
    const r = await axios.post(`${this.base}/${path}`, null, {
      params: { ...body, access_token: this.token },
      timeout: 30000,
      // Graph returns 400 with a useful body — let us read it instead of throwing raw.
      validateStatus: () => true,
    });
    if (r.status >= 400 || (r.data && r.data.error)) {
      const e = r.data && r.data.error ? r.data.error : { message: `HTTP ${r.status}` };
      this.lastError = `${e.code || r.status}/${e.error_subcode || 0}: ${e.message || "graph error"}`;
      const err = new Error(this.lastError);
      err.graph = e;
      throw err;
    }
    return r.data;
  }

  async _get(path, params = {}) {
    const r = await axios.get(`${this.base}/${path}`, {
      params: { ...params, access_token: this.token },
      timeout: 20000,
      validateStatus: () => true,
    });
    if (r.status >= 400 || (r.data && r.data.error)) {
      const e = r.data && r.data.error ? r.data.error : { message: `HTTP ${r.status}` };
      this.lastError = `${e.code || r.status}/${e.error_subcode || 0}: ${e.message || "graph error"}`;
      const err = new Error(this.lastError);
      err.graph = e;
      throw err;
    }
    return r.data;
  }

  // ---- container + publish primitives -------------------------------------

  /** POST /{ig}/media → container id. `params` are the verified media fields. */
  async createContainer(params) {
    const data = await this._post(`${this.ig}/media`, params);
    if (!data || !data.id) throw new Error("no container id returned");
    return String(data.id);
  }

  /**
   * Poll GET /{container}?fields=status_code until FINISHED. Spec: once per
   * minute for ≤5 min. We poll a touch faster (default 5s) but cap total
   * wait at `timeoutMs` so the scheduler tick doesn't hang forever; images
   * usually finish immediately, reels/video take the longest.
   */
  async pollContainer(containerId, { intervalMs = 5000, timeoutMs = 300000 } = {}) {
    const deadline = Date.now() + timeoutMs;
    // eslint-disable-next-line no-constant-condition
    while (true) {
      const data = await this._get(`${containerId}`, { fields: "status_code,status" });
      const code = String(data.status_code || "").toUpperCase();
      if (code === "FINISHED") return true;
      if (code === "ERROR" || code === "EXPIRED") {
        throw new Error(`container ${code}: ${data.status || ""}`.trim());
      }
      if (Date.now() + intervalMs > deadline) {
        throw new Error(`container not FINISHED within ${Math.round(timeoutMs / 1000)}s (last=${code || "?"})`);
      }
      await new Promise((res) => setTimeout(res, intervalMs));
    }
  }

  /** POST /{ig}/media_publish → published media id. */
  async publish(creationId) {
    const data = await this._post(`${this.ig}/media_publish`, { creation_id: creationId });
    if (!data || !data.id) throw new Error("no media id returned from media_publish");
    return String(data.id);
  }

  // ---- high-level publish helpers (mirror PHP InstagramService) ------------

  /** Single JPEG image. Image containers are publishable immediately — the
   *  spec only requires a status-poll for reels/video, so we skip it here
   *  (polling an image container can sit without ever reporting FINISHED). */
  async publishImage(imageUrl, caption = "") {
    const id = await this.createContainer({ image_url: imageUrl, caption });
    return this.publish(id);
  }

  /** Reel — needs a public HTTPS mp4 + slow encode poll. */
  async publishReel(videoUrl, caption = "", opts = {}) {
    const params = { media_type: "REELS", video_url: videoUrl, caption };
    if (opts.cover_url) params.cover_url = opts.cover_url;
    if (opts.thumb_offset != null) params.thumb_offset = opts.thumb_offset;
    // share_to_feed defaults true (Feed + Reels) unless explicitly false.
    params.share_to_feed = opts.share_to_feed === false ? "false" : "true";
    const id = await this.createContainer(params);
    await this.pollContainer(id, { timeoutMs: 300000 }); // reels encode slowly (≤5 min)
    return this.publish(id);
  }

  /** Story — image or video. Graph stickers/links unsupported (plain @-mention only). */
  async publishStory(url, isVideo = false) {
    const params = isVideo
      ? { media_type: "STORIES", video_url: url }
      : { media_type: "STORIES", image_url: url };
    const id = await this.createContainer(params);
    await this.pollContainer(id, { timeoutMs: isVideo ? 300000 : 120000 });
    return this.publish(id);
  }

  /**
   * Carousel — up to 10 children. `items` = [{ url, video }]. Children get
   * is_carousel_item=true and NO caption; parent carries the caption.
   */
  async publishCarousel(items, caption = "") {
    const children = [];
    for (const it of (Array.isArray(items) ? items : []).slice(0, 10)) {
      const child = it.video
        ? { media_type: "VIDEO", video_url: it.url, is_carousel_item: "true" }
        : { image_url: it.url, is_carousel_item: "true" };
      const cid = await this.createContainer(child);
      // Video children also need to finish encoding before the parent publishes.
      if (it.video) await this.pollContainer(cid, { timeoutMs: 300000 });
      children.push(cid);
    }
    if (children.length === 0) throw new Error("carousel needs at least one item");
    const parentId = await this.createContainer({
      media_type: "CAROUSEL",
      children: children.join(","),
      caption,
    });
    await this.pollContainer(parentId, { timeoutMs: 120000 });
    return this.publish(parentId);
  }

  /**
   * GET /{ig}/content_publishing_limit — live quota (DO NOT hardcode 50/100).
   * Returns { used, total, ok } where ok=room to publish.
   */
  async publishingLimit() {
    const data = await this._get(`${this.ig}/content_publishing_limit`, {
      fields: "config,quota_usage",
    });
    const row = (data && data.data && data.data[0]) || {};
    const used = Number(row.quota_usage || 0);
    const total = Number((row.config && row.config.quota_total) || 0) || 50;
    return { used, total, ok: used < total };
  }

  /**
   * Send a DM. recipient = IGSID (from the inbound webhook). Honors the 24h
   * window decision in the caller; here we just send messaging_type RESPONSE
   * unless a tag is supplied. Body verified: {recipient:{id}, message:{text|attachment}}.
   */
  async sendDm(recipientId, { text, attachmentUrl, attachmentType = "image", tag } = {}) {
    const message = attachmentUrl
      ? { attachment: { type: attachmentType, payload: { url: attachmentUrl } } }
      : { text: String(text || "") };
    const body = { recipient: { id: String(recipientId) }, message };
    if (tag) {
      body.messaging_type = "MESSAGE_TAG";
      body.tag = tag; // e.g. HUMAN_AGENT (up to 7 days)
    } else {
      body.messaging_type = "RESPONSE";
    }
    // messages takes a JSON body (not query params like media).
    const r = await axios.post(`${this.base}/${this.ig}/messages`, body, {
      params: { access_token: this.token },
      headers: { "Content-Type": "application/json" },
      timeout: 20000,
      validateStatus: () => true,
    });
    if (r.status >= 400 || (r.data && r.data.error)) {
      const e = (r.data && r.data.error) || { message: `HTTP ${r.status}` };
      this.lastError = `${e.code || r.status}: ${e.message || "dm error"}`;
      throw new Error(this.lastError);
    }
    return r.data;
  }

  /** Publish a comment on a media. POST /{media-id}/comments. */
  async postComment(mediaId, text) {
    const data = await this._post(`${mediaId}/comments`, { message: String(text || "").slice(0, 2000) });
    return data && data.id ? String(data.id) : null;
  }
}

export default IgGraphClient;
