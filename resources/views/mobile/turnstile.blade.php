<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verifikasi Keamanan Sinden 2</title>
    <style>
        html, body { margin: 0; padding: 0; background: transparent; }
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        #ts { width: 100%; display: flex; justify-content: center; }
    </style>
    <script>
        // Jembatan ke aplikasi Android (WebView addJavascriptInterface "SisindenTurnstile").
        function sisindenBridge() { return window.SisindenTurnstile || null; }
        function onTurnstileLoad() {
            turnstile.render('#ts', {
                sitekey: @json($siteKey),
                action: @json($action),
                theme: @json($theme),
                language: 'id',
                size: 'flexible',
                callback: function (token) { var b = sisindenBridge(); if (b) b.onToken(token); },
                'error-callback': function (code) { var b = sisindenBridge(); if (b) b.onError(String(code || '')); return true; },
                'expired-callback': function () { var b = sisindenBridge(); if (b) b.onExpired(); },
                'before-interactive-callback': function () { var b = sisindenBridge(); if (b) b.onInteractive(); }
            });
        }
    </script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad&render=explicit" async defer></script>
</head>
<body>
    <div id="ts"></div>
</body>
</html>
