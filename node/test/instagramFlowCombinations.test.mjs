// node/test/instagramFlowCombinations.test.mjs
// ============================================
// Full combination smoke test for the Instagram Node flow engine.
//
// Two layers, both against REAL code — nothing stubbed except the outside
// world (Meta's Graph API and Laravel), which are impersonated by a local
// server the service is pointed at via its own `auth.base` / `appDomain` args.
//
//   Layer 1 — the walker  (services/instagramFlowService.js)
//   Layer 2 — the HTTP chain (controllers/instagramFlowController.js mounted
//             on a real Express app: auth, 202-before-walk, consumed semantics)
//
// Run:  node test/instagramFlowCombinations.test.mjs
import http from "node:http";
import express from "express";
import { runFlow, resumeFlow, hasSession, pruneSessions } from "../services/instagramFlowService.js";
import { instagramInbound, instagramFlowHealth } from "../controllers/instagramFlowController.js";

process.env.NODE_WEBHOOK_TOKEN = "test-token";

let pass = 0, fail = 0;
const ok = (name, got, want) => {
  const g = JSON.stringify(got), w = JSON.stringify(want);
  if (g === w) { pass++; console.log(`  PASS  ${name}`); }
  else { fail++; console.log(`  FAIL  ${name}\n          got:  ${g}\n          want: ${w}`); }
};
const section = (s) => console.log(`\n=== ${s} ===`);
const quiet = (fn) => { const w = console.warn, l = console.log; console.warn = () => {}; console.log = () => {}; return Promise.resolve(fn()).finally(() => { console.warn = w; console.log = l; }); };

// ---------------------------------------------------------------------------
// Fake Meta Graph + fake Laravel
// ---------------------------------------------------------------------------
const sent = [], logged = [], asked = [];
let graphFailNext = false;

const fake = http.createServer((req, res) => {
  let body = ""; req.on("data", (c) => (body += c));
  req.on("end", () => {
    const url = req.url.split("?")[0];
    let p = {}; try { p = body ? JSON.parse(body) : {}; } catch (_) {}
    if (url.endsWith("/messages")) {
      if (graphFailNext) { graphFailNext = false; res.writeHead(400, { "Content-Type": "application/json" }); return res.end(JSON.stringify({ error: { code: 10, message: "permission denied" } })); }
      sent.push(p); res.writeHead(200, { "Content-Type": "application/json" }); return res.end(JSON.stringify({ message_id: `mid_${sent.length}` }));
    }
    if (url === "/api/instagram/flow-log") { logged.push(p); res.writeHead(200); return res.end(JSON.stringify({ ok: true })); }
    if (url === "/api/instagram/flow-node") {
      asked.push(p); res.writeHead(200, { "Content-Type": "application/json" });
      return res.end(JSON.stringify(
        p.action === "ai" ? { ok: true, reply: `AI:${p.vars?.text || ""}` }
          : p.action === "webhook" ? { ok: true, vars: { ...p.vars, hook: "done" } }
            : { ok: true }));
    }
    res.writeHead(404); res.end("{}");
  });
});
await new Promise((r) => fake.listen(0, "127.0.0.1", r));
const BASE = `http://127.0.0.1:${fake.address().port}`;
const auth = { base: BASE, ig: "1784100", token: "t" };
const common = { auth, accountId: 82, workspaceId: 5, appDomain: BASE };
const reset = () => { sent.length = 0; logged.length = 0; asked.length = 0; };

// tiny flow builder: linear chain of nodes, plus explicit extra edges
const mk = (nodes, edges) => ({ flowNodes: nodes, flowEdges: edges });
const chain = (...ids) => ids.slice(0, -1).map((s, i) => ({ source: s, sourceHandle: "out", target: ids[i + 1] }));
const T = { id: "t", type: "trigger", data: {} };
const msg = (id, text) => ({ id, type: "message", data: { text } });
const END = { id: "e", type: "end", data: {} };

// ---------------------------------------------------------------------------
section("1. Linear message chains");
reset();
await runFlow({ ...common, flow: mk([T, msg("a", "one"), msg("b", "two"), msg("c", "three"), END], chain("t", "a", "b", "c", "e")), igsid: "L1", text: "go", flowId: 1 });
ok("3 messages, in order", sent.map((s) => s.message.text), ["one", "two", "three"]);
ok("all logged to Laravel", logged.length, 3);
ok("session cleared at End", hasSession(82, "L1"), false);

reset();
await runFlow({ ...common, flow: mk([T, msg("a", "solo")], chain("t", "a")), igsid: "L2", text: "go", flowId: 1 });
ok("flow with no End node still finishes", sent.length, 1);
ok("  and clears its session", hasSession(82, "L2"), false);

reset();
await runFlow({ ...common, flow: mk([T, msg("a", "")], chain("t", "a")), igsid: "L3", text: "go", flowId: 1 });
ok("empty message text sends nothing", sent.length, 0);

// ---------------------------------------------------------------------------
section("2. Variables");
reset();
const varFlow = mk([T, msg("a", "Hi {{name}}, you said {{text}}"), msg("b", "{{missing}} stays blank"), END], chain("t", "a", "b", "e"));
await runFlow({ ...common, flow: varFlow, igsid: "V1", text: "hello", flowId: 1, vars: { name: "Sam" } });
ok("{{name}} + {{text}} substituted", sent[0].message.text, "Hi Sam, you said hello");
ok("unknown {{var}} → empty",         sent[1].message.text, " stays blank");

// ---------------------------------------------------------------------------
section("3. Condition branching");
const cond = (id, value) => ({ id, type: "condition", data: { variable: "{{text}}", operator: "contains", value } });
const condFlow = mk(
  [T, cond("c", "price"), msg("y", "YES"), msg("n", "NO"), END],
  [...chain("t", "c"), { source: "c", sourceHandle: "yes", target: "y" }, { source: "c", sourceHandle: "no", target: "n" },
   { source: "y", sourceHandle: "out", target: "e" }, { source: "n", sourceHandle: "out", target: "e" }]);
reset(); await runFlow({ ...common, flow: condFlow, igsid: "C1", text: "the price?", flowId: 1 });
ok("contains → YES", sent[0].message.text, "YES");
reset(); await runFlow({ ...common, flow: condFlow, igsid: "C2", text: "hello", flowId: 1 });
ok("no match → NO",  sent[0].message.text, "NO");

const eqFlow = mk(
  [T, { id: "c", type: "condition", data: { variable: "{{text}}", operator: "equals", value: "yes" } }, msg("y", "EQ"), msg("n", "NEQ")],
  [...chain("t", "c"), { source: "c", sourceHandle: "yes", target: "y" }, { source: "c", sourceHandle: "no", target: "n" }]);
reset(); await runFlow({ ...common, flow: eqFlow, igsid: "C3", text: "yes", flowId: 1 });
ok("equals exact → yes branch", sent[0].message.text, "EQ");
reset(); await runFlow({ ...common, flow: eqFlow, igsid: "C4", text: "yes please", flowId: 1 });
ok("equals partial → no branch", sent[0].message.text, "NEQ");

// nested conditions
const nested = mk(
  [T, cond("c1", "a"), cond("c2", "b"), msg("ab", "A+B"), msg("ao", "A only"), msg("no", "neither")],
  [...chain("t", "c1"),
   { source: "c1", sourceHandle: "yes", target: "c2" }, { source: "c1", sourceHandle: "no", target: "no" },
   { source: "c2", sourceHandle: "yes", target: "ab" }, { source: "c2", sourceHandle: "no", target: "ao" }]);
reset(); await runFlow({ ...common, flow: nested, igsid: "C5", text: "a and b", flowId: 1 });
ok("nested both true", sent[0].message.text, "A+B");
reset(); await runFlow({ ...common, flow: nested, igsid: "C6", text: "a only", flowId: 1 });
ok("nested first only", sent[0].message.text, "A only");
reset(); await runFlow({ ...common, flow: nested, igsid: "C7", text: "zzz", flowId: 1 });
ok("nested neither", sent[0].message.text, "neither");

// ---------------------------------------------------------------------------
section("4. Quick replies — every port + non-match");
const qrFlow = mk(
  [T, { id: "q", type: "buttons", data: { prompt: "Pick", options: ["Basic", "Pro"], var: "plan" } }, msg("p0", "chose Basic"), msg("p1", "chose Pro")],
  [...chain("t", "q"), { source: "q", sourceHandle: "p0", target: "p0" }, { source: "q", sourceHandle: "p1", target: "p1" }]);

reset(); await runFlow({ ...common, flow: qrFlow, igsid: "Q1", text: "hi", flowId: 1 });
ok("prompt sent + parked", [sent.length, hasSession(82, "Q1")], [1, true]);
reset(); ok("tap OPT_0 → p0", await resumeFlow({ accountId: 82, igsid: "Q1", text: "OPT_0" }) && sent[0].message.text, "chose Basic");

reset(); await runFlow({ ...common, flow: qrFlow, igsid: "Q2", text: "hi", flowId: 1 });
reset(); ok("tap OPT_1 → p1", await resumeFlow({ accountId: 82, igsid: "Q2", text: "OPT_1" }) && sent[0].message.text, "chose Pro");

reset(); await runFlow({ ...common, flow: qrFlow, igsid: "Q3", text: "hi", flowId: 1 });
reset(); ok("typed label also matches", await resumeFlow({ accountId: 82, igsid: "Q3", text: "Basic" }) && sent[0].message.text, "chose Basic");

reset(); await runFlow({ ...common, flow: qrFlow, igsid: "Q4", text: "hi", flowId: 1 });
reset();
ok("unrelated text declines",   await resumeFlow({ accountId: 82, igsid: "Q4", text: "who are you" }), false);
ok("  session kept for the tap", hasSession(82, "Q4"), true);
ok("  nothing sent",             sent.length, 0);

// ---------------------------------------------------------------------------
section("5. Ask — free text and expected answers");
const askFree = mk([T, { id: "a", type: "ask", data: { prompt: "Your email?", var: "email" } }, msg("m", "got {{email}}")], chain("t", "a", "m"));
reset(); await runFlow({ ...common, flow: askFree, igsid: "A1", text: "hi", flowId: 1 });
ok("question sent + parked", [sent[0].message.text, hasSession(82, "A1")], ["Your email?", true]);
reset(); await resumeFlow({ accountId: 82, igsid: "A1", text: "me@x.com" });
ok("answer captured into {{email}}", sent[0].message.text, "got me@x.com");

const askOpts = mk(
  [T, { id: "a", type: "ask", data: { prompt: "Yes or no?", var: "ans", options: ["yes", "no"] } }, msg("p0", "said yes"), msg("p1", "said no"), msg("el", "unclear")],
  [...chain("t", "a"),
   { source: "a", sourceHandle: "p0", target: "p0" }, { source: "a", sourceHandle: "p1", target: "p1" },
   { source: "a", sourceHandle: "else", target: "el" }]);
for (const [reply, want] of [["yes", "said yes"], ["no", "said no"], ["maybe", "unclear"]]) {
  reset(); await runFlow({ ...common, flow: askOpts, igsid: `A_${reply}`, text: "hi", flowId: 1 });
  reset(); await resumeFlow({ accountId: 82, igsid: `A_${reply}`, text: reply });
  ok(`expected-answer "${reply}"`, sent[0].message.text, want);
}

// ---------------------------------------------------------------------------
section("6. Wait node — real timing");
const waitFlow = (amount, unit) => mk([T, msg("a", "before"), { id: "w", type: "delay", data: { amount, unit } }, msg("b", "after"), END], chain("t", "a", "w", "b", "e"));
reset();
let t0 = Date.now();
await runFlow({ ...common, flow: waitFlow(0.4, "sec"), igsid: "W1", text: "go", flowId: 1 });
let el = Date.now() - t0;
ok("both messages sent",        sent.map((s) => s.message.text), ["before", "after"]);
ok("actually waited ≥350ms",    el >= 350, true);
ok("  and not absurdly long",   el < 3000, true);

reset(); t0 = Date.now();
await runFlow({ ...common, flow: waitFlow(0, "min"), igsid: "W2", text: "go", flowId: 1 });
ok("zero wait = no pause", (Date.now() - t0) < 300, true);
ok("  still sends both",   sent.length, 2);

// two customers waiting at once must not interfere
reset();
await Promise.all([
  runFlow({ ...common, flow: waitFlow(0.3, "sec"), igsid: "W3", text: "go", flowId: 1 }),
  runFlow({ ...common, flow: waitFlow(0.3, "sec"), igsid: "W4", text: "go", flowId: 1 }),
]);
ok("concurrent waits both complete", sent.length, 4);

// ---------------------------------------------------------------------------
section("7. Media");
const media = (kind, url, caption) => mk([T, { id: "x", type: "media", data: { kind, url, caption } }], chain("t", "x"));
for (const kind of ["image", "video", "audio"]) {
  reset(); await runFlow({ ...common, flow: media(kind, "https://ex.com/f", ""), igsid: `M_${kind}`, text: "x", flowId: 1 });
  ok(`${kind} → attachment`, sent[0].message.attachment.type, kind);
}
reset(); await runFlow({ ...common, flow: media("document", "https://ex.com/d.pdf", ""), igsid: "M_doc", text: "x", flowId: 1 });
ok("document → link as text (IG has no doc type)", sent[0].message.text, "https://ex.com/d.pdf");
reset(); await runFlow({ ...common, flow: media("image", "https://ex.com/a.jpg", "Look!"), igsid: "M_cap", text: "x", flowId: 1 });
ok("caption sent as a second DM", sent[1].message.text, "Look!");
reset(); await runFlow({ ...common, flow: media("image", "", ""), igsid: "M_none", text: "x", flowId: 1 });
ok("no URL → nothing sent", sent.length, 0);
reset(); await runFlow({ ...common, flow: media("image", "/storage/x.jpg", ""), igsid: "M_rel", text: "x", flowId: 1 });
ok("relative URL absolutised", sent[0].message.attachment.payload.url, `${BASE}/storage/x.jpg`);

// ---------------------------------------------------------------------------
section("8. Laravel-delegated nodes");
reset();
await runFlow({ ...common, igsid: "D1", text: "hey", flowId: 1, flow: mk(
  [T, { id: "h", type: "webhook", data: { url: "https://ex.com", save: "r" } }, { id: "a", type: "ai", data: { prompt: "p", save: "reply" } }, msg("m", "hook={{hook}}"), END],
  chain("t", "h", "a", "m", "e")) });
ok("webhook delegated",            asked[0].action, "webhook");
ok("AI delegated",                 asked[1].action, "ai");
ok("AI reply sent as DM",          sent[0].message.text, "AI:hey");
ok("webhook vars flow onward",     sent[1].message.text, "hook=done");

for (const t of ["ig_products", "ig_lead", "ig_reply_comment"]) {
  reset(); await runFlow({ ...common, flow: mk([T, { id: "n", type: t, data: {} }], chain("t", "n")), igsid: `DG_${t}`, text: "x", flowId: 1 });
  ok(`${t} delegated to Laravel`, asked[0]?.action, t);
}

// ---------------------------------------------------------------------------
section("9. Legacy Instagram nodes still run");
reset();
await runFlow({ ...common, flow: mk([T, { id: "n", type: "ig_send_dm", data: { text: "legacy dm" } }], chain("t", "n")), igsid: "LG1", text: "x", flowId: 1 });
ok("ig_send_dm", sent[0].message.text, "legacy dm");

reset();
await runFlow({ ...common, flow: mk([T, { id: "n", type: "ig_quick", data: { text: "pick", options: [{ title: "A", payload: "PA" }] } }, msg("p0", "picked A")],
  [...chain("t", "n"), { source: "n", sourceHandle: "p0", target: "p0" }]), igsid: "LG2", text: "x", flowId: 1 });
ok("ig_quick parks", hasSession(82, "LG2"), true);
reset(); await resumeFlow({ accountId: 82, igsid: "LG2", text: "PA" });
ok("  resumes on its payload", sent[0].message.text, "picked A");

reset();
await runFlow({ ...common, flow: mk([T, { id: "n", type: "ig_buttons", data: { text: "tap", buttons: [{ type: "web_url", title: "Site", url: "https://x.com" }] } }], chain("t", "n")), igsid: "LG3", text: "x", flowId: 1 });
ok("ig_buttons → button template", sent[0].message.attachment.payload.template_type, "button");
ok("  web_url button shaped",      sent[0].message.attachment.payload.buttons[0].type, "web_url");

// ig_ask — the legacy Ask node: sends the question, parks, captures the answer.
reset();
await runFlow({ ...common, flow: mk([T, { id: "n", type: "ig_ask", data: { question: "Your name?", save: "nm" } }, msg("m", "hi {{nm}}")], chain("t", "n", "m")), igsid: "LG4", text: "x", flowId: 1 });
ok("ig_ask sends the question", sent[0].message.text, "Your name?");
ok("  and parks",               hasSession(82, "LG4"), true);
reset(); await resumeFlow({ accountId: 82, igsid: "LG4", text: "Sam" });
ok("  answer saved to its var",  sent[0].message.text, "hi Sam");

// ig_ai — legacy AI node; shares the executor with `ai` but must be reachable
// under its own type name too (older flows still carry it).
reset();
await runFlow({ ...common, flow: mk([T, { id: "n", type: "ig_ai", data: { prompt: "p", save: "r" } }, msg("m", "said {{r}}")], chain("t", "n", "m")), igsid: "LG5", text: "hello", flowId: 1 });
ok("ig_ai delegated to Laravel", asked[0]?.action, "ai");
ok("  reply DM'd",               sent[0].message.text, "AI:hello");
ok("  saved to its var",         sent[1].message.text, "said AI:hello");

// ---------------------------------------------------------------------------
section("10. Robustness");
reset();
await quiet(() => runFlow({ ...common, flow: mk([msg("a", "no trigger here")], []), igsid: "R1", text: "x", flowId: 1 }));
ok("flow with no trigger sends nothing", sent.length, 0);

reset();
await runFlow({ ...common, flow: mk([T, msg("a", "one")], [{ source: "t", sourceHandle: "out", target: "ghost" }]), igsid: "R2", text: "x", flowId: 1 });
ok("dangling edge → stops cleanly", sent.length, 0);

reset();
graphFailNext = true;
await quiet(() => runFlow({ ...common, flow: mk([T, msg("a", "will fail"), msg("b", "still runs"), END], chain("t", "a", "b", "e")), igsid: "R3", text: "x", flowId: 1 }));
ok("a failed send doesn't strand the flow", sent[0]?.message?.text, "still runs");

reset();
const loop = mk([T, msg("a", "loop")], [...chain("t", "a"), { source: "a", sourceHandle: "out", target: "a" }]);
await quiet(() => runFlow({ ...common, flow: loop, igsid: "R4", text: "x", flowId: 1 }));
ok("infinite loop stopped by the guard", sent.length <= 100, true);
ok("  and it did stop",                  sent.length >= 99, true);

reset();
ok("resume with no session → false", await resumeFlow({ accountId: 82, igsid: "NOBODY", text: "hi" }), false);

reset();
await runFlow({ ...common, flow: mk([T, msg("a", "x".repeat(1200)), END], chain("t", "a", "e")), igsid: "R5", text: "x", flowId: 1 });
ok("very long text still sends", sent[0].message.text.length, 1200);

// ---------------------------------------------------------------------------
section("11. Session isolation");
reset();
await runFlow({ ...common, flow: qrFlow, igsid: "S1", text: "hi", flowId: 1 });
await runFlow({ ...common, flow: qrFlow, igsid: "S2", text: "hi", flowId: 1 });
ok("two customers parked independently", [hasSession(82, "S1"), hasSession(82, "S2")], [true, true]);
reset();
await resumeFlow({ accountId: 82, igsid: "S1", text: "OPT_0" });
ok("resuming one leaves the other parked", hasSession(82, "S2"), true);
ok("  only one reply went out",            sent.length, 1);
ok("different account, same igsid = separate", hasSession(99, "S2"), false);

// ---------------------------------------------------------------------------
section("12. HTTP layer — the real controller on a real Express app");
const app = express();
app.use(express.json({ limit: "5mb" }));
app.post("/api/instagram-flow/inbound", (req, res) => instagramInbound(req, res));
app.get("/api/instagram-flow/health", (req, res) => instagramFlowHealth(req, res));
const srv = await new Promise((r) => { const s = app.listen(0, "127.0.0.1", () => r(s)); });
const PORT = srv.address().port;
const call = async (path, body, token = "test-token", method = "POST") => {
  const res = await fetch(`http://127.0.0.1:${PORT}${path}`, {
    method,
    headers: { "Content-Type": "application/json", ...(token ? { "X-Node-Token": token } : {}) },
    ...(method === "POST" ? { body: JSON.stringify(body || {}) } : {}),
  });
  return { status: res.status, json: await res.json().catch(() => ({})) };
};

ok("health requires the token", (await call("/api/instagram-flow/health", null, "", "GET")).status, 401);
ok("health with token",         (await call("/api/instagram-flow/health", null, "test-token", "GET")).json.engine, "node");
ok("inbound rejects a bad token", (await call("/api/instagram-flow/inbound", {}, "wrong")).status, 401);
ok("inbound validates input",     (await call("/api/instagram-flow/inbound", { accountId: 0 })).status, 400);

reset();
let r = await call("/api/instagram-flow/inbound", { ...common, flow: mk([T, msg("a", "via http"), END], chain("t", "a", "e")), igsid: "H1", text: "hi", flowId: 7 });
ok("start accepted (202)",  r.status, 202);
ok("  consumed=true",       r.json.consumed, true);
ok("  mode=start",          r.json.mode, "start");
await new Promise((res) => setTimeout(res, 300));   // walk runs detached
ok("  flow really ran",     sent[0]?.message?.text, "via http");

r = await call("/api/instagram-flow/inbound", { ...common, igsid: "H_NONE", text: "hi" });
ok("no flow + no session → not consumed", [r.status, r.json.consumed], [200, false]);

// 202 must come back BEFORE a long walk finishes — that is the whole point
reset();
t0 = Date.now();
r = await call("/api/instagram-flow/inbound", { ...common, flow: waitFlow(1.2, "sec"), igsid: "H2", text: "hi", flowId: 8 });
const replyMs = Date.now() - t0;
ok("responds immediately, not after the Wait", replyMs < 800, true);
ok("  status 202",                             r.status, 202);
await new Promise((res) => setTimeout(res, 1600));
ok("  and the flow completed in the background", sent.map((s) => s.message.text), ["before", "after"]);

ok("pruneSessions is callable", typeof pruneSessions(), "number");

srv.close(); fake.close();
console.log(`\n${"=".repeat(50)}\n  ${pass} passed, ${fail} failed\n${"=".repeat(50)}`);
process.exit(fail ? 1 : 0);
