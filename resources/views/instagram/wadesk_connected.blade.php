<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instagram connected</title>
    <style>
        html, body { height: 100%; margin: 0; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            display: flex; align-items: center; justify-content: center;
            background: #f6f7f5; color: #0b1f1c; text-align: center; padding: 2rem;
        }
        .card { max-width: 320px; }
        .dot { width: 44px; height: 44px; border-radius: 50%; background: #d9f2e4; color: #0b6b4f;
               display: grid; place-items: center; margin: 0 auto 14px; }
        h1 { font-size: 18px; margin: 0 0 6px; font-weight: 600; }
        p { font-size: 13px; color: #566; margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="dot">
            <svg viewBox="0 0 16 16" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8.5l3 3 6-7"/></svg>
        </div>
        <h1>Instagram connected</h1>
        <p>You can close this window and return to WaDesk.</p>
    </div>
    <script>
        (function () {
            var id  = @json($accountId);
            var ret = @json($returnUrl);
            // Primary path: tell the WaDesk /devices tab that opened this popup,
            // then close. The opener listens for `wadesk:instagram-connected`.
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({ type: 'wadesk:instagram-connected', account_id: id }, '*');
                    window.close();
                    return;
                }
            } catch (e) {}
            // Fallback: no opener (popup blocked / opened in the same tab) —
            // bounce the top window back to WaDesk with the new account id.
            if (ret) {
                var sep = ret.indexOf('?') === -1 ? '?' : '&';
                window.location.href = ret + sep + 'ig_account=' + encodeURIComponent(id) + '&wadesk=1';
            }
        })();
    </script>
</body>
</html>
