// node/test/instagramFlowService.test.mjs
// =======================================
// End-to-end test of the Instagram Node flow engine.
//
// Stands up a throwaway HTTP server that impersonates BOTH the Meta Graph API
// and Laravel, then points the real service at it via the `auth.base` and
// `appDomain` parameters it already accepts. Nothing is mocked or stubbed —
// the actual walker runs and we assert on the HTTP calls it made.
//
// Run:  node test/instagramFlowService.test.mjs
import http from "node:http";
import { runFlow, resumeFlow, hasSession, delayMsOf } from "../services/instagramFlowService.js";

process.env.NODE_WEBHOOK_TOKEN = "test-token";

let pass = 0, fail = 0;
const ok = (name, got, want) => {
  const g = JSON.stringify(got), w = JSON.stringify(want);
  if (g === w) { pass++; console.log(`  PASS  ${name}`); }
  else { fail++; console.log(`  FAIL  ${name}\n          got:  ${g}\n          want: ${w}`); }
};

// ---- fake Graph API + fake Laravel ----------------------------------------
const sent = [];      // every Graph /messages call
const logged = [];    // every /api/instagram/flow-log call
const asked = [];     // every /api/instagram/flow-node call

const server = http.createServer((req, res) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", () => {
    const url = req.url.split("?")[0];
    let payload = {};
    try { payload = body ? JSON.parse(body) : {}; } catch (_) {}

    if (url.endsWith("/messages")) {
      sent.push(payload);
      res.writeHead(200, { "Content-Type": "application/json" });
      return res.end(JSON.stringify({ message_id: `mid_${sent.length}` }));
    }
    if (url === "/api/instagram/flow-log") {
      logged.push(payload);
      res.writeHead(200, { "Content-Type": "application/json" });
      return res.end(JSON.stringify({ ok: true }));
    }
    if (url === "/api/instagram/flow-node") {
      asked.push(payload);
      res.writeHead(200, { "Content-Type": "application/json" });
      // Pretend Laravel produced an AI reply / webhook vars.
      return res.end(JSON.stringify(
        payload.action === "ai" ? { ok: true, reply: "AI says hello" }
          : payload.action === "webhook" ? { ok: true, vars: { ...payload.vars, resp_status: "ok" } }
            : { ok: true }
      ));
    }
    res.writeHead(404); res.end("{}");
  });
});

await new Promise((r) => server.listen(0, "127.0.0.1", r));
const port = server.address().port;
const BASE = `http://127.0.0.1:${port}`;
const auth = { base: BASE, ig: "17841400000000000", token: "fake-token" };
const common = { auth, accountId: 82, workspaceId: 5, appDomain: BASE };

const flow = {
  flowNodes: [
    { id: "t",  type: "trigger",   data: {} },
    { id: "m1", type: "message",   data: { text: "Hi {{name}}, thanks for the DM!" } },
    { id: "c1", type: "condition", data: { variable: "{{text}}", operator: "contains", value: "price" } },
    { id: "q1", type: "buttons",   data: { prompt: "Which plan?", options: ["Basic", "Pro"], var: "plan" } },
    { id: "m2", type: "message",   data: { text: "No match branch." } },
    { id: "w",  type: "delay",     data: { amount: 300, unit: "sec" } },
    { id: "m3", type: "message",   data: { text: "Follow-up after the wait." } },
    { id: "e",  type: "end",       data: {} },
  ],
  flowEdges: [
    { source: "t",  sourceHandle: "out", target: "m1" },
    { source: "m1", sourceHandle: "out", target: "c1" },
    { source: "c1", sourceHandle: "yes", target: "q1" },
    { source: "c1", sourceHandle: "no",  target: "m2" },
    { source: "q1", sourceHandle: "p0",  target: "w" },
    { source: "q1", sourceHandle: "p1",  target: "m2" },
    { source: "w",  sourceHandle: "out", target: "m3" },
    { source: "m3", sourceHandle: "out", target: "e" },
  ],
};

console.log("\n=== delay unit parsing ===");
ok("5 min",  delayMsOf({ amount: 5, unit: "min" }), 300000);
ok("30 sec", delayMsOf({ amount: 30, unit: "sec" }), 30000);
ok("2 hour", delayMsOf({ amount: 2, unit: "hour" }), 7200000);
ok("0",      delayMsOf({ amount: 0, unit: "min" }), 0);

console.log("\n=== run: message → condition(yes) → quick replies → PARK ===");
sent.length = 0; logged.length = 0;
await runFlow({ ...common, flow, igsid: "IG_A", text: "what is the price", flowId: 1, vars: { name: "Sam" } });

ok("first DM sent",             sent[0]?.message?.text, "Hi Sam, thanks for the DM!");
ok("  {{name}} substituted",    sent[0]?.message?.text?.includes("Sam"), true);
ok("condition took YES branch", sent[1]?.message?.text, "Which plan?");
ok("  quick replies attached",  sent[1]?.message?.quick_replies?.map((q) => q.title), ["Basic", "Pro"]);
ok("  payloads indexed",        sent[1]?.message?.quick_replies?.map((q) => q.payload), ["OPT_0", "OPT_1"]);
ok("parked — nothing further",  sent.length, 2);
ok("session held open",         hasSession(82, "IG_A"), true);
ok("both sends logged to Laravel", logged.length, 2);

console.log("\n=== resume on the tap → REAL await through the 5-min Wait ===");
sent.length = 0;
const t0 = Date.now();
// Shorten the wait so the test is fast; the node still goes through the real
// `await sleep()` path — we assert it actually blocked.
flow.flowNodes.find((n) => n.id === "w").data = { amount: 400, unit: "sec" };
flow.flowNodes.find((n) => n.id === "w").data = { amount: 0.4, unit: "sec" };
const resumed = await resumeFlow({ accountId: 82, igsid: "IG_A", text: "OPT_0" });
const elapsed = Date.now() - t0;

ok("resume consumed the tap",   resumed, true);
ok("waited for real (>=350ms)", elapsed >= 350, true);
ok("follow-up sent AFTER wait", sent[0]?.message?.text, "Follow-up after the wait.");
ok("session cleared at End",    hasSession(82, "IG_A"), false);

console.log("\n=== condition NO branch ===");
sent.length = 0;
await runFlow({ ...common, flow, igsid: "IG_B", text: "hello there", flowId: 1 });
ok("NO branch taken", sent[1]?.message?.text, "No match branch.");

console.log("\n=== Laravel-delegated nodes (AI + webhook) ===");
const flow2 = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "h", type: "webhook", data: { url: "https://example.com/x", save: "resp" } },
    { id: "a", type: "ai",      data: { prompt: "be nice", save: "reply" } },
    { id: "e", type: "end",     data: {} },
  ],
  flowEdges: [
    { source: "t", sourceHandle: "out", target: "h" },
    { source: "h", sourceHandle: "out", target: "a" },
    { source: "a", sourceHandle: "out", target: "e" },
  ],
};
sent.length = 0; asked.length = 0;
await runFlow({ ...common, flow: flow2, igsid: "IG_C", text: "hi", flowId: 2 });
ok("webhook delegated to Laravel", asked[0]?.action, "webhook");
ok("AI delegated to Laravel",      asked[1]?.action, "ai");
ok("  AI vars carried forward",    asked[1]?.vars?.resp_status, "ok");
ok("AI reply DM'd to the customer", sent[0]?.message?.text, "AI says hello");

console.log("\n=== media node ===");
const flow3 = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "x", type: "media",   data: { kind: "image", url: "https://ex.com/a.jpg", caption: "Look!" } },
  ],
  flowEdges: [{ source: "t", sourceHandle: "out", target: "x" }],
};
sent.length = 0;
await runFlow({ ...common, flow: flow3, igsid: "IG_D", text: "x", flowId: 3 });
ok("image attachment sent", sent[0]?.message?.attachment?.type, "image");
ok("  url passed through",  sent[0]?.message?.attachment?.payload?.url, "https://ex.com/a.jpg");
ok("caption as its own DM", sent[1]?.message?.text, "Look!");

console.log("\n=== unknown node type must be LOUD, not silent ===");
const flow4 = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "z", type: "list",    data: {} },        // WhatsApp-only, no IG executor
    { id: "m", type: "message", data: { text: "after" } },
  ],
  flowEdges: [
    { source: "t", sourceHandle: "out", target: "z" },
    { source: "z", sourceHandle: "out", target: "m" },
  ],
};
sent.length = 0;
let warned = false;
const origWarn = console.warn;
console.warn = (...a) => { if (String(a[0]).includes("no executor")) warned = true; origWarn(...a); };
await runFlow({ ...common, flow: flow4, igsid: "IG_E", text: "x", flowId: 4 });
console.warn = origWarn;
ok("warned about the unsupported node", warned, true);
ok("walk continued past it",            sent[0]?.message?.text, "after");

server.close();
console.log(`\n${"=".repeat(46)}\n  ${pass} passed, ${fail} failed\n${"=".repeat(46)}`);
process.exit(fail ? 1 : 0);
