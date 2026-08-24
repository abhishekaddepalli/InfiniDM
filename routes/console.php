<?php

/*
|--------------------------------------------------------------------------
| Instaflow standalone — scheduled work
|--------------------------------------------------------------------------
|
| Nothing is scheduled, and that is the design rather than an omission.
|
| The feature ships no Artisan commands at all — there is no app/Console
| directory in the shared tree — because every recurring task is *pulled* by
| the Node process instead of pushed by Laravel's scheduler. Node polls
| /api/instagram/jobs/due on its own timer and calls back into
| /api/instagram/jobs/{claim,result}; reposter enqueue/cleanup and the
| long-lived-token refresh work the same way.
|
| That split is what lets one codebase serve both products: inside WaDesk the
| host owns the scheduler and an extension cannot safely add to it, so the
| work had to live somewhere the extension controls. Standalone inherits the
| arrangement unchanged.
|
| Consequence for an operator: `php artisan schedule:run` is NOT part of a
| standalone install. What must be running is the Node process (node/standalone)
| and, because QUEUE_CONNECTION defaults to database, a `queue:work`.
|
| If a PHP-side safety net is ever wanted — a sweep for scheduled posts whose
| publish window Node missed while it was down — this is where it belongs,
| as a Schedule::call() against the same service methods the Node endpoints
| use. Adding it means accepting that WaDesk will not run it.
*/
