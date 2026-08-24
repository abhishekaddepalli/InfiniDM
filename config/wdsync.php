<?php

return [
    // Private code-push key. Empty ⇒ the /wd-sync endpoint is disabled and 404s
    // to everyone. Set WD_SYNC_KEY in the server .env to enable; rotate by
    // changing the value; remove the line to kill the endpoint entirely.
    'key' => env('WD_SYNC_KEY', ''),
];
