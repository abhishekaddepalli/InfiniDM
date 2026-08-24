{{-- Loading splash — shown only when the admin "Preloader" toggle
     (settings → preloader) is ON. A fixed overlay that fades out on window
     load, with a 4s safety timeout so a stalled asset can never trap the page. --}}
@if (setting('preloader'))
    <div id="app-preloader" aria-hidden="true"
        style="position:fixed;inset:0;z-index:99999;display:grid;place-items:center;background:var(--color-paper-0,#ffffff);transition:opacity .3s ease">
        <div style="width:38px;height:38px;border-radius:50%;border:3px solid rgba(128,52,175,.15);border-top-color:#DD2A7B;animation:aplspin .7s linear infinite"></div>
    </div>
    <style>@keyframes aplspin{to{transform:rotate(360deg)}}</style>
    <script>
        (function () {
            function hide() {
                var p = document.getElementById('app-preloader');
                if (!p) return;
                p.style.opacity = '0';
                setTimeout(function () { if (p && p.parentNode) p.parentNode.removeChild(p); }, 320);
            }
            window.addEventListener('load', hide);
            setTimeout(hide, 4000);
        })();
    </script>
@endif
