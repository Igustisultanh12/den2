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
    </style>
    {{-- SDK yang sama dengan modul Vicon web (VideoCallModal.vue) --}}
    <script src="https://download.agora.io/sdk/release/AgoraRTC_N-4.20.2.js"></script>
</head>
<body>
<div id="remote"></div>
<div id="local"></div>
<script>
    // Halaman ini hanya menangani media. Sinyal panggilan (memanggil, menerima,
    // mengakhiri) dikelola aplikasi Android melalui API /api/mobile/chat/*.
    var client = null, mic = null, cam = null, cameras = [], camIndex = 0, joined = false;

    function emit(type, data) {
        try { if (window.SisindenVicon) window.SisindenVicon.onEvent(type, JSON.stringify(data || {})); } catch (e) {}
    }

    async function viconJoin(appId, channel, token, uid, withVideo) {
        if (client) return;
        if (!window.AgoraRTC) { emit('error', { message: 'SDK Agora tidak dapat dimuat. Periksa koneksi internet.' }); return; }
        try {
            AgoraRTC.setLogLevel(3);
            client = AgoraRTC.createClient({ mode: 'rtc', codec: 'vp8' });
            client.on('user-published', async function (user, mediaType) {
                try {
                    await client.subscribe(user, mediaType);
                    if (mediaType === 'video' && user.videoTrack) { user.videoTrack.play(document.getElementById('remote')); emit('remote-video', { on: true }); }
                    if (mediaType === 'audio' && user.audioTrack) { user.audioTrack.play(); }
                    emit('remote-joined', { media: mediaType });
                } catch (e) { emit('warn', { message: String(e && e.message || e) }); }
            });
            client.on('user-unpublished', function (user, mediaType) {
                if (mediaType === 'video') emit('remote-video', { on: false });
            });
            client.on('user-left', function () { emit('remote-left', {}); });
            client.on('connection-state-change', function (cur) { emit('state', { state: cur }); });

            await client.join(appId, channel, token || null, Number(uid) || null);
            joined = true;
            try {
                mic = await AgoraRTC.createMicrophoneAudioTrack({ AEC: true, ANS: true, AGC: true });
            } catch (e) { emit('warn', { message: 'Mikrofon tidak tersedia' }); }
            if (withVideo) {
                try {
                    cam = await AgoraRTC.createCameraVideoTrack({ encoderConfig: '720p_2', facingMode: 'user' });
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
