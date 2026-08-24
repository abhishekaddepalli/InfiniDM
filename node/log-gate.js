/*
 * ── Global Node log gate ──────────────────────────────────────────────────
 * One switch for every console.log() in the worker + its controllers/services,
 * so you never hunt down individual calls. Same idea as WaDesk's log-gate.js.
 *
 * Imported FIRST in index.js, so it also gates logs emitted while the other
 * modules load. console.error / console.warn ALWAYS print so real problems stay
 * visible.
 *
 * DEFAULT = logs ON (this is a self-hosted worker; you want to see it working).
 * To run QUIET in production, set  INSTAFLOW_LOGS=off  in node/.env.
 * (WADESK_LOGS=off is also honoured, for parity with the WaDesk node.)
 */
import './config/config.js'; // side-effect: loads node/.env into process.env first

const off = process.env.INSTAFLOW_LOGS === 'off' || process.env.WADESK_LOGS === 'off';
if (off) {
    const noop = () => {};
    console.log = noop;
    console.info = noop;
    console.debug = noop;
}
