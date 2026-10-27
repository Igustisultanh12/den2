<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'; 
import axios from 'axios'; 

const props = defineProps({
    stats: Object,
    recent_logs: Array,
    combined_activities: Array, 
    pending_users: Array, 
    today_attendance: Object,
    recent_attendances: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isCommanderOrAdmin = computed(() => {
    const role = (currentUser.value?.role || '').toLowerCase();
    return Boolean(currentUser.value?.is_admin) || role === 'komandan' || role === 'pasops' || currentUser.value?.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

// Jam Digital Real-Time Kedinasan
const currentTimeStr = ref('');
let clockTimer = null;

const updateClock = () => {
    const now = new Date();
    currentTimeStr.value = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    }) + ' WIB';
};

// Deteksi Otomatis Merk HP & Seri Perangkat
const detectDeviceModel = async () => {
    if (navigator.userAgentData && navigator.userAgentData.getHighEntropyValues) {
        try {
            const data = await navigator.userAgentData.getHighEntropyValues(['model', 'platform', 'platformVersion']);
            if (data.model) {
                const brandObj = navigator.userAgentData.brands?.find(b => !b.brand.includes('Brand') && !b.brand.includes('Chromium'));
                const brand = brandObj ? brandObj.brand + ' ' : '';
                return `${brand}${data.model} (${data.platform || 'Mobile'})`.trim();
            }
            if (data.platform) {
                return `${data.platform} (${navigator.userAgentData.mobile ? 'Mobile' : 'Desktop'})`;
            }
        } catch (e) {}
    }

    const ua = navigator.userAgent || '';
    if (/iPhone/i.test(ua)) return 'Apple iPhone';
    if (/iPad/i.test(ua)) return 'Apple iPad';

    const androidMatch = ua.match(/Android[^;]+;\s*([^;]+?)\s*(?:Build|\))/i);
    if (androidMatch && androidMatch[1]) {
        let model = androidMatch[1].trim();
        if (model.startsWith('SM-') || /samsung/i.test(ua)) return `Samsung ${model}`;
        if (/Xiaomi|Redmi|POCO/i.test(ua) || /M2\d{3}|2\d{6}/i.test(model)) return `Xiaomi ${model}`;
        if (/OPPO|CPH\d{4}/i.test(ua) || model.startsWith('CPH')) return `OPPO ${model}`;
        if (/vivo|V2\d{3}/i.test(ua) || model.startsWith('V2')) return `Vivo ${model}`;
        if (/Infinix|X\d{3}/i.test(ua) || model.startsWith('X')) return `Infinix ${model}`;
        if (/Realme|RMX\d{4}/i.test(ua) || model.startsWith('RMX')) return `Realme ${model}`;
        return `Android (${model})`;
    }

    if (/Windows NT/i.test(ua)) return 'Windows PC';
    if (/Macintosh/i.test(ua)) return 'Apple Mac';
    if (/Linux/i.test(ua)) return 'Linux PC';

    return 'Perangkat Seluler / Komputer';
};

// Modal & Form Presensi Kehadiran
const isAttendanceModalOpen = ref(false);
const isDetectingLocation = ref(false);
const isSubmittingAttendance = ref(false);
const locationStatusText = ref('Mendeteksi GPS...');

const attendanceForm = ref({
    status: 'hadir',
    notes: '',
    latitude: null,
    longitude: null,
    location_name: '',
    device_model: '',
    photo: null,
});

// --- FITUR KAMERA, PEMINDAI WAJAH & DETEKSI SENYUM ---
const videoRef = ref(null);
const canvasRef = ref(null);
const cameraActive = ref(false);
const cameraError = ref(null);
const modelsLoaded = ref(false);
const faceDetected = ref(false);
const smileScore = ref(0);
const isCountingDown = ref(false);
const countdownValue = ref(3);
const isFlash = ref(false);
const capturedPhoto = ref(null);
const capturedSmileScore = ref(0);
const smileThreshold = 50; // Ambang batas 50% senyum agar lebih responsif dan alami

let mediaStream = null;
let detectionInterval = null;
let countdownInterval = null;
let detectCanvas = null;
let detectCtx = null;

// Efek Suara Audio Sintesis (Tanpa ketergantungan berkas eksternal)
const playBeep = (freq = 520) => {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, ctx.currentTime);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.13);
    } catch (e) {}
};

const playShutterSound = () => {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(800, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(300, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.14);
    } catch (e) {}
};

// Pemuatan Pustaka face-api dari Direktori Lokal Aplikasi dengan Fallback CDN
const loadFaceApiScript = () => {
    return new Promise((resolve, reject) => {
        if (window.faceapi) {
            resolve(window.faceapi);
            return;
        }
        const existingScript = document.getElementById('face-api-script');
        if (existingScript) {
            existingScript.addEventListener('load', () => {
                if (typeof faceapi !== 'undefined' && !window.faceapi) window.faceapi = faceapi;
                resolve(window.faceapi);
            });
            existingScript.addEventListener('error', reject);
            return;
        }
        const script = document.createElement('script');
        script.id = 'face-api-script';
        script.src = '/js/face-api.min.js';
        script.async = true;
        script.onload = () => {
            if (typeof faceapi !== 'undefined' && !window.faceapi) window.faceapi = faceapi;
            resolve(window.faceapi);
        };
        script.onerror = () => {
            // Fallback CDN jika skrip lokal terhalang
            const cdnScript = document.createElement('script');
            cdnScript.id = 'face-api-cdn-script';
            cdnScript.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js';
            cdnScript.onload = () => {
                if (typeof faceapi !== 'undefined' && !window.faceapi) window.faceapi = faceapi;
                resolve(window.faceapi);
            };
            cdnScript.onerror = reject;
            document.head.appendChild(cdnScript);
        };
        document.head.appendChild(script);
    });
};

const initFaceDetector = async () => {
    try {
        await loadFaceApiScript();
        const api = window.faceapi || (typeof faceapi !== 'undefined' ? faceapi : null);
        if (!api) {
            console.warn('[FACE_AI] Pustaka face-api belum terpasang.');
            return;
        }
        window.faceapi = api;

        if (!modelsLoaded.value) {
            try {
                // 1. Muat model lokal dari /models/face
                await Promise.all([
                    api.nets.tinyFaceDetector.loadFromUri('/models/face'),
                    api.nets.faceExpressionNet.loadFromUri('/models/face')
                ]);
                modelsLoaded.value = true;
                console.log('[FACE_AI] Model wajah lokal berhasil dimuat.');
            } catch (localErr) {
                console.warn('[FACE_AI] Gagal memuat model lokal, mencoba CDN fallback...', localErr);
                // 2. Fallback CDN jika akses berkas statis lokal bermasalah
                const CDN_MODEL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
                await Promise.all([
                    api.nets.tinyFaceDetector.loadFromUri(CDN_MODEL),
                    api.nets.faceExpressionNet.loadFromUri(CDN_MODEL)
                ]);
                modelsLoaded.value = true;
                console.log('[FACE_AI] Model wajah dari CDN berhasil dimuat.');
            }
        }
        startDetectionLoop();
    } catch (err) {
        console.error('[FACE_AI] Inisialisasi sensor AI wajah gagal:', err);
    }
};

const startDetectionLoop = () => {
    if (detectionInterval) clearInterval(detectionInterval);
    const api = window.faceapi;
    if (!api || !modelsLoaded.value) return;

    let consecutiveSmiles = 0;

    detectionInterval = setInterval(async () => {
        const video = videoRef.value;
        if (!video || video.paused || video.ended || !video.videoWidth || !video.videoHeight || isCountingDown.value || capturedPhoto.value) {
            return;
        }

        try {
            // Buffer Canvas 2D Intermediary:
            // Mengatasi bug kritis WebKit / iOS Safari di mana WebGL gl.texImage2D langsung dari HTMLVideoElement WebRTC menghasilkan frame hitam kosong (0x0).
            if (!detectCanvas) {
                detectCanvas = document.createElement('canvas');
                detectCtx = detectCanvas.getContext('2d', { willReadFrequently: true });
            }

            const srcW = video.videoWidth;
            const srcH = video.videoHeight;
            // Skala target maksimal lebar 480px untuk menjaga performa rendering cepat dan konsumsi memori ringan di mobile Safari
            const targetW = Math.min(srcW, 480);
            const targetH = Math.round(srcH * (targetW / srcW));

            if (detectCanvas.width !== targetW || detectCanvas.height !== targetH) {
                detectCanvas.width = targetW;
                detectCanvas.height = targetH;
            }

            // Render frame WebRTC ke kanvas 2D terlebih dahulu
            detectCtx.drawImage(video, 0, 0, targetW, targetH);

            // Gunakan inputSize 320 dan scoreThreshold 0.20 agar deteksi wajah responsif dan adaptif
            const options = new api.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.20 });
            let result = null;
            try {
                result = await api.detectSingleFace(detectCanvas, options).withFaceExpressions();
            } catch (innerErr) {
                try {
                    result = await api.detectSingleFace(detectCanvas, options);
                } catch (canvasErr) {
                    result = await api.detectSingleFace(video, options);
                }
            }

            if (result) {
                faceDetected.value = true;
                const happyScore = result.expressions?.happy || 0;
                smileScore.value = Math.round(happyScore * 100);

                if (smileScore.value >= smileThreshold) {
                    consecutiveSmiles++;
                    // Jika senyum dipertahankan minimal 2 siklus (~400ms), picu hitung mundur pemotretan otomatis
                    if (consecutiveSmiles >= 2 && !isCountingDown.value && !capturedPhoto.value) {
                        triggerCountdownSequence();
                    }
                } else {
                    consecutiveSmiles = 0;
                }
            } else {
                faceDetected.value = false;
                smileScore.value = 0;
                consecutiveSmiles = 0;
            }
        } catch (e) {
            // Abaikan glitch frame sementara
        }
    }, 200);
};

const triggerCountdownSequence = () => {
    if (isCountingDown.value || capturedPhoto.value) return;
    isCountingDown.value = true;
    countdownValue.value = 3;
    playBeep(520);

    countdownInterval = setInterval(() => {
        countdownValue.value--;
        if (countdownValue.value > 0) {
            playBeep(520 + (3 - countdownValue.value) * 150);
        } else {
            clearInterval(countdownInterval);
            countdownInterval = null;
            takeSnapshot();
        }
    }, 1000);
};

const takeSnapshot = () => {
    if (!videoRef.value || !canvasRef.value) return;

    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }

    playShutterSound();
    isFlash.value = true;
    setTimeout(() => { isFlash.value = false; }, 250);

    const video = videoRef.value;
    const canvas = canvasRef.value;
    const width = video.videoWidth || 640;
    const height = video.videoHeight || 480;
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    // Mirror horizontal agar sesuai tampilan cermin
    ctx.translate(width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, width, height);
    ctx.setTransform(1, 0, 0, 1, 0, 0);

    const photoDataUrl = canvas.toDataURL('image/jpeg', 0.88);
    capturedPhoto.value = photoDataUrl;
    capturedSmileScore.value = smileScore.value;
    attendanceForm.value.photo = photoDataUrl;
    isCountingDown.value = false;

    if (detectionInterval) {
        clearInterval(detectionInterval);
        detectionInterval = null;
    }
};

const retakePhoto = () => {
    capturedPhoto.value = null;
    attendanceForm.value.photo = null;
    faceDetected.value = false;
    smileScore.value = 0;
    isCountingDown.value = false;
    startDetectionLoop();
};

const startCamera = async () => {
    cameraActive.value = true;
    cameraError.value = null;
    capturedPhoto.value = null;
    attendanceForm.value.photo = null;
    faceDetected.value = false;
    smileScore.value = 0;
    isCountingDown.value = false;

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 640 }
            },
            audio: false
        });
        mediaStream = stream;
        await nextTick();
        if (videoRef.value) {
            const video = videoRef.value;
            video.setAttribute('autoplay', 'true');
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.setAttribute('muted', 'true');
            video.muted = true;
            video.playsInline = true;
            video.srcObject = stream;

            await new Promise((resolve) => {
                let resolved = false;
                const onReady = () => {
                    if (!resolved) {
                        resolved = true;
                        video.play().then(resolve).catch(resolve);
                    }
                };
                video.onloadedmetadata = onReady;
                video.onloadeddata = onReady;
                setTimeout(onReady, 500);
            });
        }
        initFaceDetector();
    } catch (err) {
        console.error('Gagal mengakses kamera:', err);
        cameraError.value = 'Peramban tidak dapat mengakses modul kamera. Pastikan izin kamera telah diberikan pada pengaturan browser Anda.';
    }
};

const stopCamera = () => {
    if (detectionInterval) {
        clearInterval(detectionInterval);
        detectionInterval = null;
    }
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    detectCanvas = null;
    detectCtx = null;
    cameraActive.value = false;
};

// Modal Detail Foto Presensi (Untuk Perbesar Gambar)
const isPhotoModalOpen = ref(false);
const activePhotoDetail = ref(null);

const viewPhotoDetail = (attRecord) => {
    activePhotoDetail.value = attRecord;
    isPhotoModalOpen.value = true;
};

const openAttendanceModal = async () => {
    isAttendanceModalOpen.value = true;
    isDetectingLocation.value = true;
    locationStatusText.value = 'Mendeteksi titik koordinat GPS dan perangkat Anda...';

    attendanceForm.value.device_model = await detectDeviceModel();

    // Jalankan Kamera
    startCamera();

    // Deteksi GPS Geolocation
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                attendanceForm.value.latitude = lat;
                attendanceForm.value.longitude = lng;
                locationStatusText.value = `Koordinat: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;

                try {
                    const res = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=id`);
                    if (res.ok) {
                        const d = await res.json();
                        const loc = d.locality || '';
                        const city = d.city || d.principalSubdivision || '';
                        if (loc && city) {
                            attendanceForm.value.location_name = `${loc}, ${city}`;
                            locationStatusText.value = `${loc}, ${city}`;
                        } else if (city || loc) {
                            attendanceForm.value.location_name = city || loc;
                            locationStatusText.value = city || loc;
                        }
                    }
                } catch (e) {}
                isDetectingLocation.value = false;
            },
            (err) => {
                locationStatusText.value = 'Akses lokasi GPS wajib. Izinkan akses lokasi pada browser, lalu tekan "Coba lagi".';
                isDetectingLocation.value = false;
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    } else {
        locationStatusText.value = 'Peramban tidak mendukung GPS. Gunakan peramban lain atau aplikasi Sinden 2 Android.';
        isDetectingLocation.value = false;
    }
};

const retryLocation = () => {
    attendanceForm.value.latitude = null;
    attendanceForm.value.longitude = null;
    isDetectingLocation.value = true;
    locationStatusText.value = 'Mendeteksi titik koordinat GPS...';
    navigator.geolocation?.getCurrentPosition(
        (pos) => {
            attendanceForm.value.latitude = pos.coords.latitude;
            attendanceForm.value.longitude = pos.coords.longitude;
            locationStatusText.value = `Koordinat: ${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)}`;
            isDetectingLocation.value = false;
        },
        () => {
            locationStatusText.value = 'Akses lokasi GPS wajib. Izinkan akses lokasi pada browser, lalu tekan "Coba lagi".';
            isDetectingLocation.value = false;
        },
        { enableHighAccuracy: true, timeout: 15000 }
    );
};

const closeAttendanceModal = () => {
    stopCamera();
    isAttendanceModalOpen.value = false;
};

const submitAttendance = () => {
    if (!attendanceForm.value.photo) {
        alert('Perhatian: Harap lakukan pemotretan wajah dengan tersenyum terlebih dahulu sebelum menyimpan presensi kehadiran.');
        return;
    }
    if (attendanceForm.value.latitude === null || attendanceForm.value.longitude === null) {
        alert('Perhatian: Lokasi GPS wajib. Izinkan akses lokasi pada browser lalu tekan "Coba lagi".');
        return;
    }

    isSubmittingAttendance.value = true;
    router.post(route('attendances.store'), { ...attendanceForm.value, smile_score: capturedSmileScore.value }, {
        preserveScroll: true,
        onSuccess: () => {
            closeAttendanceModal();
            isSubmittingAttendance.value = false;
        },
        onError: (err) => {
            alert('Gagal mencatat presensi: ' + Object.values(err)[0]);
            isSubmittingAttendance.value = false;
        }
    });
};

const openMap = (lat, lng) => {
    if (lat && lng) {
        window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank');
    }
};

const sendPreciseLocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                axios.post('/update-location', {
                    latitude: lat,
                    longitude: lng
                }).catch(() => {});
            },
            () => {},
            { enableHighAccuracy: true } 
        );
    }
};

onMounted(() => {
    updateClock();
    clockTimer = setInterval(updateClock, 1000);
    sendPreciseLocation();
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
    stopCamera();
});
</script>

<template>
    <Head title="Dashboard Utama - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 sm:space-y-8 font-sans">
            
            <!-- Hero Welcome Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-[#E2E8F0] shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 font-extrabold text-[10px] uppercase rounded-full tracking-wider">Dashboard Analitik</span>
                        <span class="text-slate-400 text-xs font-semibold">Live System SINDEN</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Selamat datang kembali, {{ $page.props.auth.user.pangkat || '' }} {{ $page.props.auth.user.name }}
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed max-w-2xl">
                        Sistem Informasi Detasemen Intelijen. Siap mendukung efisiensi kedinasan, pemantauan presensi kehadiran dengan deteksi wajah dan senyum, pengawasan lokasi, dan administrasi naskah intelijen hari ini.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link :href="route('letters.index')" class="px-5 py-3 bg-blue-600 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider hover:bg-blue-700 transition shadow-md shadow-blue-500/20">
                        + Buat Surat Baru
                    </Link>
                </div>
            </div>

            <!-- KARTU PRESENSI KEHADIRAN PERSONEL (OPSIONAL) -->
            <div v-if="today_attendance" class="bg-emerald-50/70 border border-emerald-200/90 rounded-3xl p-6 shadow-xs flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="flex items-start gap-4">
                    <!-- Thumbnail Foto Presensi -->
                    <div v-if="today_attendance.photo || today_attendance.photo_url" class="shrink-0">
                        <div class="relative group cursor-pointer" @click="viewPhotoDetail(today_attendance)" title="Klik untuk memperbesar foto presensi">
                            <img 
                                :src="today_attendance.photo_url || ('/storage/' + today_attendance.photo)" 
                                class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-2xl border-2 border-emerald-500 shadow-sm transition group-hover:scale-105" 
                                alt="Foto Presensi"
                            />
                            <span class="absolute -bottom-1 -right-1 bg-emerald-700 text-white text-[8px] font-black uppercase px-1.5 py-0.5 rounded-full shadow-xs tracking-wider border border-white">
                                Senyum OK
                            </span>
                        </div>
                    </div>
                    <div v-else class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-xl shrink-0 shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 bg-emerald-600 text-white font-extrabold text-[10px] uppercase rounded-full tracking-wider">
                                Presensi Hari Ini Tercatat: {{ today_attendance.status.replace('_', ' ').toUpperCase() }}
                            </span>
                            <span class="text-xs font-mono font-bold text-emerald-800">
                                Pukul {{ today_attendance.time_in ? today_attendance.time_in.substring(0, 5) : '-' }} WIB
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-1 text-xs text-slate-600 pt-1">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Perangkat:</span>
                                <span class="font-bold text-slate-800">{{ today_attendance.device || 'Perangkat Terdaftar' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Alamat IP:</span>
                                <span class="font-mono font-bold text-slate-800">{{ today_attendance.ip_address || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Titik Lokasi:</span>
                                <span class="font-bold text-slate-800 truncate block max-w-xs" :title="today_attendance.location_name">
                                    {{ today_attendance.location_name || 'GPS Kedinasan' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-end lg:self-center">
                    <button 
                        v-if="today_attendance.latitude && today_attendance.longitude"
                        @click="openMap(today_attendance.latitude, today_attendance.longitude)"
                        class="px-4 py-2.5 bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-extrabold uppercase tracking-wider transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                        title="Buka titik koordinat di Google Maps"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Peta
                    </button>
                    <button 
                        @click="openAttendanceModal"
                        class="px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Perbarui Presensi
                    </button>
                </div>
            </div>

            <!-- JIKA BELUM PRESENSI HARI INI -->
            <div v-else class="bg-slate-900 text-white rounded-3xl p-6 sm:p-7 shadow-lg border border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-1.5 max-w-2xl">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 bg-amber-500/20 text-amber-400 font-extrabold text-[10px] uppercase rounded-full border border-amber-500/30 tracking-wider">
                            Presensi Personel (Opsional)
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-400">{{ currentTimeStr }}</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-white tracking-tight flex items-center gap-2">
                        <span>Catat Kehadiran Mandiri dengan Verifikasi Wajah & Senyum</span>
                    </h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-medium">
                        Fitur presensi bersifat mandiri dan opsional. Peramban menggunakan kamera untuk mendeteksi wajah dan senyuman personel, lalu secara otomatis menghitung mundur 1-2-3 untuk memotret kehadiran, serta merekam IP, merk HP, dan titik lokasi penugasan.
                    </p>
                </div>

                <div class="shrink-0 self-end md:self-center">
                    <button 
                        @click="openAttendanceModal"
                        class="px-5 py-3.5 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider transition shadow-lg shadow-blue-600/30 flex items-center gap-2.5 cursor-pointer active:scale-95"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Catat Kehadiran (Kamera & Senyum)
                    </button>
                </div>
            </div>

            <!-- Stats Widgets Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- Presensi Hari Ini Stat -->
                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Presensi Hari Ini</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.today_attendances || 0 }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Personel Tercatat Hadir</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Buku Nomor Surat</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.total_logs }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Total Registrasi Surat Keluar</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Arsip Berkas</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.total_archives }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Dokumen Terarsip</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Master Personel</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.active_personnel }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Pengguna Sistem Aktif</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                </div>

            </div>

            <!-- TABEL REKAPITULASI PRESENSI PERSONEL HARI INI (KHUSUS PIMPINAN & ADMIN) -->
            <div v-if="isCommanderOrAdmin && recent_attendances" class="bg-white rounded-3xl border border-[#E2E8F0] p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Pengawasan Kedinasan</span>
                        <h4 class="text-sm font-extrabold text-slate-900 uppercase">
                            Rekapitulasi Presensi Kehadiran Personel Hari Ini
                        </h4>
                    </div>
                    <span class="text-xs font-extrabold bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl uppercase tracking-wider self-start sm:self-auto">
                        Total: {{ recent_attendances.length }} Personel
                    </span>
                </div>

                <div v-if="recent_attendances.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                                <th class="p-3">Personel</th>
                                <th class="p-3">Foto Presensi</th>
                                <th class="p-3">Pukul</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Perangkat (Merk & Seri HP)</th>
                                <th class="p-3">Alamat IP</th>
                                <th class="p-3">Lokasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            <tr v-for="att in recent_attendances" :key="att.id" class="hover:bg-slate-50/80 transition">
                                <td class="p-3">
                                    <div class="font-extrabold text-slate-900">{{ att.user?.name || '-' }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ att.user?.pangkat }} NRP. {{ att.user?.nrp || '-' }}</div>
                                </td>
                                <td class="p-3">
                                    <div v-if="att.photo || att.photo_url" class="flex items-center gap-2">
                                        <button 
                                            type="button"
                                            @click="viewPhotoDetail(att)"
                                            class="relative group cursor-pointer focus:outline-none"
                                            title="Klik untuk memperbesar foto presensi"
                                        >
                                            <img 
                                                :src="att.photo_url || ('/storage/' + att.photo)" 
                                                class="w-10 h-10 object-cover rounded-xl border border-slate-200 shadow-xs transition group-hover:scale-110 group-hover:border-blue-500" 
                                                alt="Foto"
                                            />
                                            <span class="absolute -bottom-1 -right-1 bg-emerald-600 text-white text-[7px] font-bold px-1 rounded-full">
                                                Senyum
                                            </span>
                                        </button>
                                    </div>
                                    <span v-else class="text-[10px] text-slate-400 italic">Tanpa Foto</span>
                                </td>
                                <td class="p-3 font-mono font-extrabold text-indigo-700">
                                    {{ att.time_in ? att.time_in.substring(0, 5) : '-' }} WIB
                                </td>
                                <td class="p-3">
                                    <span :class="{
                                        'bg-emerald-100 text-emerald-800': att.status === 'hadir',
                                        'bg-blue-100 text-blue-800': att.status === 'piket',
                                        'bg-amber-100 text-amber-800': att.status === 'dinas_luar',
                                        'bg-purple-100 text-purple-800': att.status === 'izin',
                                    }" class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider">
                                        {{ att.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="font-bold text-slate-800">{{ att.device || '-' }}</span>
                                </td>
                                <td class="p-3 font-mono text-[11px] text-slate-500">
                                    {{ att.ip_address || '-' }}
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs truncate max-w-[180px]" :title="att.location_name">{{ att.location_name || '-' }}</span>
                                        <button 
                                            v-if="att.latitude && att.longitude"
                                            @click="openMap(att.latitude, att.longitude)"
                                            class="p-1 hover:bg-slate-200 text-blue-600 rounded-md transition cursor-pointer"
                                            title="Lihat Peta"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-8 text-xs text-slate-400 font-semibold italic">
                    Belum ada personel yang melakukan presensi hari ini.
                </div>
            </div>

            <!-- Content Grid: Activity Logs & Pending Registrations -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Log Aktivitas Terkini -->
                <div v-if="combined_activities" class="bg-white rounded-3xl border border-[#E2E8F0] p-6 lg:col-span-2 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 bg-blue-600 rounded-full animate-pulse"></span> Log Aktivitas & Akses Terkini
                        </h4>
                    </div>
                    
                    <div v-if="combined_activities.length > 0" class="space-y-3">
                        <div v-for="activity in combined_activities" :key="activity.id" 
                             :class="activity.type === 'ADMIN_ACTION' ? 'bg-amber-50/50 border-amber-200/60' : 'bg-slate-50/70 border-slate-100'" class="flex items-center justify-between p-4 rounded-2xl border transition hover:shadow-xs">
                            
                            <div class="min-w-0 flex-1 pr-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-extrabold text-slate-900 uppercase truncate max-w-[200px]">{{ activity.user_name }}</span>
                                    <span v-if="activity.type === 'ADMIN_ACTION'" class="text-[8px] font-extrabold bg-amber-500 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Action</span>
                                    <span v-else class="text-[8px] font-extrabold bg-blue-600 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Access</span>
                                </div>
                                <p :class="activity.type === 'ADMIN_ACTION' ? 'text-amber-800' : 'text-slate-600'" class="text-[11px] font-medium tracking-tight truncate">
                                    {{ activity.type === 'ADMIN_ACTION' ? activity.description : 'Akses Portal: ' + activity.location }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                 <span class="text-[10px] font-mono font-bold text-slate-400 uppercase">{{ activity.login_at }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold italic">Menunggu sinkronisasi aktivitas...</div>
                </div>

                <!-- Antrean Registrasi Pending -->
                <div v-if="pending_users" class="bg-white rounded-3xl border border-[#E2E8F0] p-6 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 bg-rose-500 rounded-full"></span> Antrean Registrasi Pending
                        </h4>
                    </div>

                    <div v-if="pending_users.length > 0" class="space-y-3">
                        <div v-for="pending in pending_users" :key="pending.id" class="flex items-center justify-between p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-extrabold text-slate-900 block uppercase truncate">{{ pending.name }}</span>
                                <span class="text-[10px] text-slate-400 font-bold block mt-0.5">NRP. {{ pending.nrp }}</span>
                            </div>
                            <Link :href="route('users.index')" class="ms-3 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-900 hover:text-white text-slate-700 rounded-xl shadow-xs transition-all text-[10px] font-extrabold uppercase tracking-wider"> Tinjau
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/40 rounded-2xl border border-dashed border-slate-200">0 Antrean Pending</div>
                </div>

            </div>

        </div>

        <!-- MODAL FORMULIR PRESENSI KEHADIRAN DENGAN KAMERA & DETEKSI SENYUM -->
        <Teleport to="body">
            <div v-if="isAttendanceModalOpen" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-black shadow-xs">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Verifikasi Biometrik Wajah</span>
                                <h3 class="text-base font-extrabold text-slate-900">Presensi Kehadiran Mandiri</h3>
                            </div>
                        </div>
                        <button @click="closeAttendanceModal" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitAttendance" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        
                        <!-- KOMPONEN VIEWPORT KAMERA & DETEKTOR SENYUM -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    Perekaman Wajah & Senyuman
                                </label>
                                <span v-if="capturedPhoto" class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md uppercase">
                                    Foto Terverifikasi (Senyum: {{ capturedSmileScore }}%)
                                </span>
                                <span v-else-if="faceDetected" class="text-[10px] font-extrabold text-blue-600 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-md uppercase animate-pulse">
                                    Wajah Terdeteksi - Silakan Senyum
                                </span>
                                <span v-else class="text-[10px] font-extrabold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md uppercase">
                                    Posisikan Wajah ke Kamera
                                </span>
                            </div>

                            <!-- Area Video & Snapshot Box -->
                            <div class="relative w-full h-64 sm:h-72 rounded-2xl overflow-hidden bg-slate-950 border-2 border-slate-800 flex items-center justify-center shadow-inner">
                                
                                <!-- Canvas Tersembunyi untuk Capture -->
                                <canvas ref="canvasRef" class="hidden"></canvas>

                                <!-- Layar Flash Efek Rana -->
                                <div 
                                    v-if="isFlash" 
                                    class="absolute inset-0 bg-white z-40 transition-opacity duration-200 pointer-events-none"
                                ></div>

                                <!-- 1. TAMPILAN HASIL FOTO YANG TELAH DIAMBIL -->
                                <div v-if="capturedPhoto" class="relative w-full h-full">
                                    <img :src="capturedPhoto" class="w-full h-full object-cover" alt="Foto Hasil Presensi" />
                                    <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between bg-slate-950/80 backdrop-blur-md px-3 py-2 rounded-xl border border-slate-700/50">
                                        <div class="text-white">
                                            <span class="text-[10px] font-extrabold uppercase text-emerald-400 block">Status: Senyum Terpenuhi</span>
                                            <span class="text-[9px] text-slate-400 font-mono">{{ currentTimeStr }}</span>
                                        </div>
                                        <button 
                                            type="button" 
                                            @click="retakePhoto" 
                                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-[10px] font-extrabold uppercase tracking-wider transition border border-slate-600 cursor-pointer"
                                        >
                                            Ambil Ulang Foto
                                        </button>
                                    </div>
                                </div>

                                <!-- 2. LIVE CAMERA STREAM VIEW -->
                                <div v-show="!capturedPhoto && !cameraError" class="relative w-full h-full flex items-center justify-center">
                                    <video 
                                        ref="videoRef" 
                                        autoplay 
                                        playsinline 
                                        webkit-playsinline="true"
                                        :playsinline="true"
                                        muted 
                                        class="w-full h-full object-cover scale-x-[-1]"
                                    ></video>

                                    <!-- HUD Reticle Pemindai Wajah -->
                                    <div class="absolute inset-4 sm:inset-6 pointer-events-none flex flex-col justify-between">
                                        <!-- Pojok Atas Reticle -->
                                        <div class="flex justify-between items-start">
                                            <div :class="faceDetected ? 'border-emerald-400' : 'border-slate-500'" class="w-6 h-6 border-t-2 border-l-2 rounded-tl-lg transition-colors duration-300"></div>
                                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-slate-950/70 backdrop-blur-xs rounded-full border border-slate-700/60">
                                                <span :class="faceDetected ? 'bg-emerald-400 animate-ping' : 'bg-amber-400'" class="w-1.5 h-1.5 rounded-full"></span>
                                                <span class="text-[9px] font-mono font-bold text-white uppercase tracking-wider">
                                                    {{ faceDetected ? 'TARGET LOCKED' : 'SEARCHING FACE' }}
                                                </span>
                                            </div>
                                            <div :class="faceDetected ? 'border-emerald-400' : 'border-slate-500'" class="w-6 h-6 border-t-2 border-r-2 rounded-tr-lg transition-colors duration-300"></div>
                                        </div>

                                        <!-- Target Lingkar Wajah Tengah -->
                                        <div class="flex items-center justify-center">
                                            <div 
                                                :class="[
                                                    faceDetected ? (smileScore >= smileThreshold ? 'border-emerald-400 bg-emerald-500/15 ring-4 ring-emerald-500/30' : 'border-cyan-400 bg-cyan-500/10') : 'border-slate-600/70 border-dashed',
                                                    isCountingDown ? 'scale-110' : ''
                                                ]"
                                                class="w-44 h-52 sm:w-48 sm:h-56 rounded-full border-2 transition-all duration-300 flex flex-col items-center justify-center p-4 text-center"
                                            >
                                                <!-- Overlay Hitung Mundur 1 2 3 -->
                                                <div v-if="isCountingDown" class="animate-bounce">
                                                    <span class="text-6xl sm:text-7xl font-black text-white drop-shadow-[0_4px_16px_rgba(0,0,0,0.9)]">
                                                        {{ countdownValue }}
                                                    </span>
                                                    <span class="block text-[10px] font-black uppercase text-emerald-300 tracking-widest mt-1">
                                                        TAHAN SENYUM!
                                                    </span>
                                                </div>
                                                <div v-else-if="faceDetected" class="text-white space-y-1">
                                                    <span class="text-xs font-black uppercase tracking-wider block drop-shadow-md text-emerald-300">
                                                        {{ smileScore >= smileThreshold ? 'SENYUM TERDETEKSI!' : 'AYO SENYUM' }}
                                                    </span>
                                                    <span class="text-[9px] font-bold text-slate-200 block drop-shadow-md">
                                                        {{ smileScore >= smileThreshold ? 'Memulai hitung mundur...' : 'Tersenyumlah untuk memotret' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pojok Bawah Reticle & Indikator Gauge Senyum -->
                                        <div class="flex justify-between items-end">
                                            <div :class="faceDetected ? 'border-emerald-400' : 'border-slate-500'" class="w-6 h-6 border-b-2 border-l-2 rounded-bl-lg transition-colors duration-300"></div>
                                            
                                            <!-- Gauge Meter Senyum Real-Time -->
                                            <div class="w-48 sm:w-56 px-3 py-1.5 bg-slate-950/85 backdrop-blur-md rounded-xl border border-slate-700/80 shadow-lg text-center">
                                                <div class="flex items-center justify-between text-[9px] font-bold text-slate-300 mb-1">
                                                    <span class="uppercase tracking-wider">Tingkat Senyum:</span>
                                                    <span :class="smileScore >= smileThreshold ? 'text-emerald-400 font-black' : 'text-amber-400'" class="font-mono text-[10px]">
                                                        {{ smileScore }}%
                                                    </span>
                                                </div>
                                                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                                                    <div 
                                                        :style="{ width: Math.min(smileScore, 100) + '%' }" 
                                                        :class="smileScore >= smileThreshold ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-amber-500 to-yellow-400'"
                                                        class="h-full transition-all duration-150 rounded-full"
                                                    ></div>
                                                </div>
                                            </div>

                                            <div :class="faceDetected ? 'border-emerald-400' : 'border-slate-500'" class="w-6 h-6 border-b-2 border-r-2 rounded-br-lg transition-colors duration-300"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. ERROR AKSES KAMERA -->
                                <div v-if="cameraError" class="p-6 text-center text-white space-y-3">
                                    <div class="w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 mx-auto flex items-center justify-center font-bold text-xl">
                                        !
                                    </div>
                                    <p class="text-xs text-rose-300 font-medium max-w-xs mx-auto leading-relaxed">
                                        {{ cameraError }}
                                    </p>
                                    <button 
                                        type="button" 
                                        @click="startCamera" 
                                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold tracking-wider transition"
                                    >
                                        Coba Akses Kamera Lagi
                                    </button>
                                </div>
                            </div>

                            <!-- Tombol Rana Manual (Opsi Tambahan bila Cahaya Redup / Akses HP) -->
                            <div v-if="!capturedPhoto && !cameraError" class="flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 px-1">
                                <span class="text-[10px] text-slate-400 text-center sm:text-left">
                                    *Otomatis memotret saat senyum, atau gunakan tombol di samping:
                                </span>
                                <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                                    <button 
                                        type="button" 
                                        @click="takeSnapshot" 
                                        :disabled="isCountingDown" 
                                        class="flex-1 sm:flex-initial px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-extrabold uppercase tracking-wider transition cursor-pointer shadow-xs disabled:opacity-50 flex items-center justify-center gap-1"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Ambil Foto Sekarang
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="triggerCountdownSequence" 
                                        :disabled="isCountingDown" 
                                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold uppercase tracking-wider transition cursor-pointer disabled:opacity-50 border border-slate-200"
                                    >
                                        {{ isCountingDown ? 'Mundur (' + countdownValue + 's)...' : 'Mundur (3s)' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Identitas Personel Card -->
                        <div class="p-3 bg-slate-50 border border-slate-200/70 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Personel:</span>
                                <span class="font-extrabold text-slate-900 text-xs">{{ currentUser.pangkat }} {{ currentUser.name }}</span>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-500">NRP. {{ currentUser.nrp || '-' }}</span>
                        </div>

                        <!-- Status Kehadiran -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Status Kehadiran</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <label :class="attendanceForm.status === 'hadir' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="hadir" class="hidden" />
                                    Hadir Dinas
                                </label>
                                <label :class="attendanceForm.status === 'piket' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="piket" class="hidden" />
                                    Piket / Jaga
                                </label>
                                <label :class="attendanceForm.status === 'dinas_luar' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="dinas_luar" class="hidden" />
                                    Dinas Luar
                                </label>
                                <label :class="attendanceForm.status === 'izin' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="izin" class="hidden" />
                                    Izin Dinas
                                </label>
                            </div>
                        </div>

                        <!-- Perangkat yang Terdeteksi -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Perangkat yang Digunakan (Merk & Seri HP)</label>
                            <input 
                                type="text" 
                                v-model="attendanceForm.device_model" 
                                required
                                placeholder="Contoh: Samsung Galaxy A54 5G, Xiaomi Redmi Note 12, dll." 
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Lokasi GPS & Wilayah -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Titik Lokasi / Koordinat GPS</label>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 truncate pr-2">
                                    <span v-if="isDetectingLocation" class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                    <span v-else-if="attendanceForm.latitude" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span v-else class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    <span class="font-bold text-slate-800 truncate">{{ locationStatusText }}</span>
                                </div>
                                <span v-if="attendanceForm.latitude" class="text-[10px] font-mono font-bold text-blue-600 shrink-0">GPS Aktif</span>
                                <button
                                    v-else-if="!isDetectingLocation"
                                    type="button"
                                    class="text-[10px] font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg px-2.5 py-1 shrink-0"
                                    @click="retryLocation"
                                >Coba lagi</button>
                            </div>
                        </div>

                        <!-- Catatan Opsional -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Catatan Tambahan (Opsional)</label>
                            <textarea 
                                v-model="attendanceForm.notes" 
                                rows="2" 
                                placeholder="Tuliskan keterangan penugasan atau catatan operasional jika ada..." 
                                class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="closeAttendanceModal" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingAttendance || !attendanceForm.photo"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ isSubmittingAttendance ? 'Merekam Presensi...' : 'Simpan Presensi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL PRATINJAU FOTO PRESENSI LENGKAP -->
        <Teleport to="body">
            <div v-if="isPhotoModalOpen && activePhotoDetail" class="fixed inset-0 z-[170] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Verifikasi Foto Kehadiran</span>
                            <h3 class="text-sm font-extrabold text-slate-900">
                                {{ activePhotoDetail.user?.pangkat }} {{ activePhotoDetail.user?.name || currentUser.name }}
                            </h3>
                        </div>
                        <button @click="isPhotoModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="relative rounded-2xl overflow-hidden bg-slate-950 border border-slate-200 shadow-inner aspect-[4/3]">
                            <img 
                                :src="activePhotoDetail.photo_url || ('/storage/' + activePhotoDetail.photo)" 
                                class="w-full h-full object-cover" 
                                alt="Foto Lengkap Presensi" 
                            />
                            <div class="absolute bottom-3 left-3 bg-emerald-600 text-white text-[9px] font-extrabold uppercase px-2.5 py-1 rounded-lg shadow-md tracking-wider">
                                Senyum Terverifikasi
                            </div>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span class="text-slate-400 font-bold uppercase text-[10px]">Waktu Presensi:</span>
                                <span class="font-mono font-extrabold text-slate-800">
                                    {{ activePhotoDetail.time_in ? activePhotoDetail.time_in.substring(0, 5) : '-' }} WIB
                                </span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span class="text-slate-400 font-bold uppercase text-[10px]">Status:</span>
                                <span class="font-bold text-slate-800 uppercase text-[11px]">
                                    {{ (activePhotoDetail.status || 'hadir').replace('_', ' ') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span class="text-slate-400 font-bold uppercase text-[10px]">Perangkat:</span>
                                <span class="font-bold text-slate-800 text-[11px] text-right truncate max-w-[200px]">
                                    {{ activePhotoDetail.device || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span class="text-slate-400 font-bold uppercase text-[10px]">Alamat IP:</span>
                                <span class="font-mono font-bold text-slate-800">
                                    {{ activePhotoDetail.ip_address || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-start pt-1">
                                <span class="text-slate-400 font-bold uppercase text-[10px]">Lokasi:</span>
                                <span class="font-bold text-slate-800 text-right text-[11px] max-w-[220px]">
                                    {{ activePhotoDetail.location_name || '-' }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button 
                                type="button" 
                                @click="isPhotoModalOpen = false" 
                                class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Tutup Pratinjau
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
