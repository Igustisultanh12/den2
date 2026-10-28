<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>Vicon Sinden 2</title>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #020617; overflow: hidden; }
        #remote { position: absolute; inset: 0; background: #020617; }
        #local { position: absolute; top: 96px; right: 14px; width: 112px; height: 160px; border-radius: 18px; overflow: hidden;
                 border: 2px solid rgba(255,255,255,.35); box-shadow: 0 8px 20px rgba(0,0,0,.45); background: #0f172a; z-index: 2; display: none; }
        #remote video, #local video { object-fit: cover !important; }
        /* Keterangan saat video lawan bicara belum/ tidak tersedia (di bawah video). */
        #hint { position: absolute; left: 0; right: 0; top: 45%; text-align: center; color: rgba(255,255,255,.75);
                font: 600 14px/1.4 system-ui, sans-serif; padding: 0 32px; z-index: 1; pointer-events: none; }
        #remote { z-index: 0; }
    </style>
    {{-- SDK disajikan dari server Sinden 2 sendiri (sama dengan web); CDN Agora hanya cadangan. --}}
    <script src="{{ url('/mobile/agora-sdk') }}"></script>
    <script>
        if (!window.AgoraRTC) {
            document.write('<script src="https://download.agora.io/sdk/release/AgoraRTC_N-4.20.2.js"><\/script>');
        }
    </script>
</head>
<body>
<div id="hint">Menunggu video lawan bicara…</div>
<div id="remote"></div>
<div id="local"></div>
<script>
    // Halaman ini hanya menangani media. Sinyal panggilan (memanggil, menerima,
    // mengakhiri) dikelola aplikasi Android melalui API /api/mobile/chat/*.
    var client = null, mic = null, cam = null, cameras = [], camIndex = 0, joined = false;

    function emit(type, data) {
        try { if (window.SisindenVicon) window.SisindenVicon.onEvent(type, JSON.stringify(data || {})); } catch (e) {}
    }

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function hint(text) {
        var el = document.getElementById('hint');
        el.textContent = text || '';
        el.style.display = text ? 'block' : 'none';
    }

    var frameWatch = null;

    // Pasang video lawan bicara; ulangi bila gagal sesaat.
    async function playRemote(user, attempt) {
        attempt = attempt || 0;
        if (!user || !user.videoTrack) return;
        try {
            user.videoTrack.play(document.getElementById('remote'), { fit: 'cover' });
            emit('remote-video', { on: true });
            watchFrames(user);
        } catch (e) {
            if (attempt < 5) { await sleep(300); return playRemote(user, attempt + 1); }
            emit('warn', { message: 'Video lawan bicara gagal ditampilkan' });
        }
    }

    /**
     * Pastikan gambar video benar-benar masuk. Bila setelah 4 detik resolusi yang
     * diterima masih 0 (video tersendat/terputus diam-diam), subscribe ulang & putar ulang.
     */
    function watchFrames(user) {
        clearInterval(frameWatch);
        var checks = 0, resubscribed = 0;
        frameWatch = setInterval(async function () {
            checks++;
            var stats = {};
            try { stats = (user.videoTrack && user.videoTrack.getStats()) || {}; } catch (e) {}
            var width = stats.receiveResolutionWidth || 0;
            if (width > 0) { hint(''); clearInterval(frameWatch); return; }
            hint('Menerima video lawan bicara…');
            if (checks % 4 === 0 && resubscribed < 3 && client) {
                resubscribed++;
                try {
                    await client.unsubscribe(user, 'video');
                    await client.subscribe(user, 'video');
                    if (user.videoTrack) user.videoTrack.play(document.getElementById('remote'), { fit: 'cover' });
                } catch (e) {}
            }
            if (checks >= 20) {
                clearInterval(frameWatch);
                hint('Video lawan bicara belum diterima — sinyal lemah. Suara tetap tersambung.');
                emit('warn', { message: 'Video lawan bicara belum diterima (sinyal lemah).' });
            }
        }, 1000);
    }

    // Gabung kanal; bila UDP diblokir jaringan, ulangi lewat Cloud Proxy Agora (UDP lalu TCP/TLS 443).
    async function joinWithFallback(appId, channel, token, uid) {
        var modes = [null, 3, 5], lastErr = null;
        for (var i = 0; i < modes.length; i++) {
            try {
                if (modes[i] !== null) {
                    try { await client.leave(); } catch (e) {}
                    client.startProxyServer(modes[i]);
                    emit('state', { state: 'PROXY_' + modes[i] });
                }
                await Promise.race([
                    client.join(appId, channel, token || null, Number(uid) || null),
                    new Promise(function (_, rej) { setTimeout(function () { rej(new Error('JOIN_TIMEOUT')); }, 12000); })
                ]);
                return;
            } catch (e) {
                lastErr = e;
                var msg = String((e && e.message) || '').toLowerCase();
                if ((e && e.code === 'INVALID_PARAMS') || msg.indexOf('token') >= 0 || msg.indexOf('appid') >= 0) break;
            }
        }
        throw lastErr || new Error('Gagal bergabung ke kanal vicon');
    }

    async function createCamera() {
        try {
            return await AgoraRTC.createCameraVideoTrack({ encoderConfig: '480p_1', facingMode: 'user', optimizationMode: 'motion' });
        } catch (e) {
            // Kamera kadang masih dipakai sesaat (mis. kamera presensi): coba sekali lagi.
            await sleep(800);
            return await AgoraRTC.createCameraVideoTrack({ encoderConfig: '480p_1' });
        }
    }

    async function viconJoin(appId, channel, token, uid, withVideo) {
        if (client) return;
        if (!window.AgoraRTC) { emit('error', { message: 'SDK Agora tidak dapat dimuat. Periksa koneksi internet.' }); return; }
        try {
            AgoraRTC.setLogLevel(3);
            AgoraRTC.onAutoplayFailed = function () {
                // WebView sudah mengizinkan autoplay; bila tetap gagal, coba putar ulang audio.
                setTimeout(function () { (client && client.remoteUsers || []).forEach(function (u) { try { u.audioTrack && u.audioTrack.play(); } catch (e) {} }); }, 500);
            };
            client = AgoraRTC.createClient({ mode: 'rtc', codec: 'vp8' });
            client.on('user-published', async function (user, mediaType) {
                try {
                    await client.subscribe(user, mediaType);
                    if (mediaType === 'video') await playRemote(user);
                    if (mediaType === 'audio' && user.audioTrack) user.audioTrack.play();
                    emit('remote-joined', { media: mediaType });
                } catch (e) {
                    emit('warn', { message: String(e && e.message || e) });
                    // Subscribe bisa gagal sesaat saat jaringan baru pulih: coba lagi sekali.
                    await sleep(1500);
                    try {
                        await client.subscribe(user, mediaType);
                        if (mediaType === 'video') await playRemote(user);
                        if (mediaType === 'audio' && user.audioTrack) user.audioTrack.play();
                        emit('remote-joined', { media: mediaType });
                    } catch (e2) {}
                }
            });
            client.on('user-unpublished', function (user, mediaType) {
                if (mediaType === 'video') {
                    clearInterval(frameWatch);
                    hint('Kamera lawan bicara dimatikan');
                    emit('remote-video', { on: false });
                }
            });
            // Lawan bicara masuk tanpa kamera/mikrofon tetap dianggap tersambung.
            client.on('user-joined', function (user) {
                emit('remote-joined', { media: 'none' });
                // Belum ada video dalam beberapa detik → lawan bicara tanpa kamera.
                setTimeout(function () {
                    if (!user.hasVideo) hint('Lawan bicara tidak mengaktifkan kamera');
                }, 5000);
            });
            // reason: "Quit" = menutup panggilan; "ServerTimeOut" = sinyal putus sebentar.
            client.on('user-left', function (user, reason) { emit('remote-left', { reason: reason || '' }); });
            client.on('connection-state-change', function (cur) { emit('state', { state: cur }); });

            await joinWithFallback(appId, channel, token, uid);
            joined = true;
            try {
                mic = await AgoraRTC.createMicrophoneAudioTrack({ AEC: true, ANS: true, AGC: true });
            } catch (e) { emit('warn', { message: 'Mikrofon tidak tersedia' }); }
            if (withVideo) {
                try {
                    cam = await createCamera();
                    var local = document.getElementById('local');
                    local.style.display = 'block';
                    cam.play(local);
                    try { cameras = await AgoraRTC.getCameras(); } catch (e) {}
                } catch (e) { emit('warn', { message: 'Kamera tidak tersedia' }); }
            }
            var tracks = [mic, cam].filter(Boolean);
            if (tracks.length) await client.publish(tracks);
            emit('joined', { video: !!cam, audio: !!mic });
        } catch (e) {
            emit('error', { message: String(e && e.message || e) });
        }
    }

    async function viconLeave() {
        clearInterval(frameWatch);
        try { if (mic) { mic.stop(); mic.close(); } } catch (e) {}
        try { if (cam) { cam.stop(); cam.close(); } } catch (e) {}
        mic = null; cam = null;
        try { if (client) { await client.leave(); client.removeAllListeners(); } } catch (e) {}
        client = null; joined = false;
        document.getElementById('local').style.display = 'none';
    }

    async function viconMic(on) { try { if (mic) await mic.setEnabled(!!on); } catch (e) {} }

    async function viconCam(on) {
        try {
            if (cam) { await cam.setEnabled(!!on); document.getElementById('local').style.display = on ? 'block' : 'none'; }
        } catch (e) {}
    }

    async function viconFlip() {
        try {
            if (!cam || cameras.length < 2) return;
            camIndex = (camIndex + 1) % cameras.length;
            await cam.setDevice(cameras[camIndex].deviceId);
        } catch (e) {}
    }

    window.addEventListener('load', function () { emit('ready', { sdk: !!window.AgoraRTC }); });
</script>
</body>
</html>
