// ESM entry alias for panels that expect an "index-cpanel.js" startup file, or
// for running the worker directly with `node index-cpanel.js`.
//
// index.js reads the port from process.env.PORT (which cPanel sets) and starts
// the scheduler on listen, so this file only needs to load it. It exists so a
// hosting panel can point its "Application startup file" at a recognizable name.
//
// NOTE: cPanel's "Setup Node.js App" (Passenger) require()s the startup file,
// which CANNOT load an ES Module — for that flow use app.cjs or server.cjs
// instead. Use THIS file only when the panel runs the entry with `node <file>`.
import './index.js';
