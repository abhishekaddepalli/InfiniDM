<?php

/*
|--------------------------------------------------------------------------
| Instaflow standalone — API routes
|--------------------------------------------------------------------------
|
| Intentionally empty. The file exists because bootstrap/app.php names it in
| withRouting(api: ...) and Laravel requires the path to resolve.
|
| Every API endpoint this product has is already declared in the shared
| routes/instagram.php, which registers its own Route::middleware('api')
| ->prefix('api') group for the Node bridge — /api/instagram/jobs/due, claim,
| result, reposter/enqueue, reposter/cleanup, refresh-tokens, flow-log and
| flow-node. That group ships with the feature so the addon and standalone
| builds expose byte-identical URLs; Node is configured once and works against
| either product.
|
| Do not mirror those routes here. This file is loaded under the framework's
| own `api` group, so a second declaration would stack middleware differently
| from the shared one and the duplicate names would shadow it.
*/
