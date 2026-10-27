<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ref, shallowRef, markRaw, computed, nextTick, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';

// Konfigurasi Worker PDF.js Core
const PDF_JS_VERSION = '3.11.174';
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/pdf.worker.min.js`;

const props = defineProps({ 
  requests: Object,
  skhppRequests: Object,
  targetDoc: Object,
  targetSkhpp: Object
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const commanderSignature = computed(() => page.props.settings?.commander_signature || 'signatures/komandan_ttd.png');
const isCommander = computed(() => user.value?.role === 'komandan');

// State Active Tab
const activeTab = ref('skhpp'); // 'skhpp' | 'pdf'

// State Modals
const isModalOpen = ref(false);
const isRevisionModalOpen = ref(false);
const isPreviewOpen = ref(false);
const isSkhppPreviewOpen = ref(false);

const selectedReqId = ref(null);
const selectedReq = ref(null);
const selectedSkhpp = ref(null);

const isAdjusting = ref(false);
const isLoadingPdf = ref(false);

// Multi-Page PDF State (Gunakan shallowRef agar tidak di-Proxy oleh Vue 3)
const currentPage = ref(1);
const totalPages = ref(1);
const pdfDoc = shallowRef(null);

// Signature Drag Position
// Signature Drag Position (Default 1:1 Presisi e-Materai Standard)
const signaturePos = ref({ x: 100, y: 100 });
const signatureSize = ref({ width: 90, height: 90 });
const pageSignatures = ref({});

// Forms
const isOperatorConfiguring = ref(false);
const form = useForm({ 
  subject: '', 
  document_title: '', 
  letter_number: '', 
  person_name: '', 
  pangkat_nrp: '', 
  jabatan: '', 
  peruntukan: '', 
  file: null,
  x: 0.58,
  y: 0.72,
  width: 0.15,
  target_page: 1,
  pages_data: null
});
const decisionForm = useForm({ 
  status: '', 
  x: 0, 
  y: 0, 
  width: 0, 
  canvas_width: 0, 
  target_page: 1, 
  apply_to_all: false,
  pages_data: null,
  note: '', 
  file: null, 
  _method: 'PATCH' 
});

// --- LOGIKA DIRECT LINK DOKUMEN KOMANDAN ---
const fallbackCopy = (text) => {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  textArea.style.top = "-999999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Link Direct Disalin',
      text: 'Tautan dokumen langsung siap dikirim ke Komandan',
      showConfirmButton: false,
      timer: 2000
    });
  } catch (err) {
    window.prompt("Salin Link Direct Komandan berikut:", text);
  }
  document.body.removeChild(textArea);
};

const copyDirectLink = (type, item) => {
  if (!item || !item.id) return;
  const baseUrl = window.location.origin;
  const url = type === 'skhpp'
    ? `${baseUrl}/signature-requests?open_skhpp=${item.id}&tab=skhpp`
    : `${baseUrl}/signature-requests?open_id=${item.id}&tab=pdf`;

  const copySuccess = () => {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Link Direct Disalin',
      text: 'Tautan dokumen langsung siap dikirim ke Komandan',
      showConfirmButton: false,
      timer: 2000
    });
  };

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(copySuccess).catch(() => {
      fallbackCopy(url);
    });
  } else {
    fallbackCopy(url);
  }
};

// --- LOGIKA AUTO REFRESH TABEL & AUTO OPEN DIRECT LINK ---
let refreshTimer = null;
const autoRefreshData = () => {
  if (!isPreviewOpen.value && !isSkhppPreviewOpen.value && !isModalOpen.value && !isRevisionModalOpen.value && !form.processing && !decisionForm.processing) {
    router.reload({ only: ['requests', 'skhppRequests'], preserveScroll: true, preserveState: true });
  }
};

let resizeTimer = null;
const handleWindowResize = () => {
  if (isPreviewOpen.value && pdfDoc.value) {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(async () => {
      await renderPdfPage(currentPage.value);
      loadPagePos();
    }, 150);
  }
};

onMounted(() => { 
  refreshTimer = setInterval(autoRefreshData, 1000);
  window.addEventListener('resize', handleWindowResize);

  // Periksa apakah ada direct link dokumen untuk Komandan
  nextTick(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const openDocId = urlParams.get('open_id') || urlParams.get('id');
    const openSkhppId = urlParams.get('open_skhpp') || urlParams.get('skhpp_id');

    if (openDocId || props.targetDoc) {
      activeTab.value = 'pdf';
      const target = props.targetDoc || props.requests?.data?.find(r => r.id == openDocId);
      if (target) {
        openPdfPreview(target);
      }
    } else if (openSkhppId || props.targetSkhpp) {
      activeTab.value = 'skhpp';
      const target = props.targetSkhpp || props.skhppRequests?.data?.find(s => s.id == openSkhppId);
      if (target) {
        openSkhppPreviewModal(target);
      }
    } else if (tabParam === 'pdf') {
      activeTab.value = 'pdf';
    }
  });
});

onUnmounted(() => { 
  if (refreshTimer) clearInterval(refreshTimer); 
  window.removeEventListener('resize', handleWindowResize);
});

// --- LOGIKA SKHPP APPROVE / REJECT ---
const approveSkhpp = (skhpp) => {
  const defaultSeq = skhpp.nomor_urut || '';
  const katCode = (skhpp.kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';

  Swal.fire({
    title: 'OTORISASI & TERBITKAN SKHPP',
    html: `
      <div class="text-left text-xs space-y-3 font-sans">
        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
          <p class="font-black text-blue-600 text-[10px] uppercase mb-0.5">${katCode}</p>
          <p class="font-bold text-slate-800">${skhpp.nama}</p>
          <p class="text-slate-500">${skhpp.pangkat_korps_nrp || skhpp.nik || '-'}</p>
        </div>

        <div>
          <label class="block font-bold uppercase mb-1 text-slate-700 text-[10px]">Nomor Urut ${skhpp.kategori_personel === 'perusahaan' ? 'SKHPP-P' : 'SKHPP-D'} (Isi jika mau loncati nomor)</label>
          <input id="swal-custom-seq" type="number" value="${defaultSeq}" class="w-full text-xs font-bold p-2.5 border rounded-xl" placeholder="Kosongkan untuk nomor urut otomatis ${skhpp.kategori_personel === 'perusahaan' ? 'SKHPP-P' : 'SKHPP-D'}" />
          <span class="text-[9px] text-slate-400 mt-1 block">*Penomoran SKHPP-P dan SKHPP-D terpisah & independen.</span>
        </div>

        <p class="text-emerald-700 font-bold text-[10px] bg-emerald-50 p-2 rounded-lg border border-emerald-200"> Diterbitkan nomor resmi ${katCode}, QR Code TTD Komandan, dan tersinkron ke Buku Agenda.
        </p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'YA, SETUJUI & TERBITKAN',
    cancelButtonText: 'BATAL',
    preConfirm: () => {
      const seqVal = document.getElementById('swal-custom-seq').value;
      return { custom_nomor_urut: seqVal };
    }
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('skhpp.approve', skhpp.id), {
        custom_nomor_urut: res.value.custom_nomor_urut
      }, {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('BERHASIL DISAHKAN', 'SKHPP Resmi telah ditandatangani Komandan & tersinkron ke Buku Agenda.', 'success');
        }
      });
    }
  });
};

const rejectSkhpp = (skhpp) => {
  Swal.fire({
    title: 'TOLAK / MINTA REVISI SKHPP',
    text: "Tuliskan catatan revisi untuk staf/operator pengaju:",
    input: 'textarea',
    inputPlaceholder: 'Tuliskan alasan penolakan atau instruksi revisi...',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    confirmButtonText: 'KIRIM REVISI',
    cancelButtonText: 'BATAL',
    inputValidator: (val) => { if (!val) return 'Catatan revisi wajib diisi!'; }
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('skhpp.reject', skhpp.id), { catatan_revisi: res.value }, {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('REVISI DIKIRIM', 'Catatan revisi berhasil dikirim ke pengaju.', 'warning');
        }
      });
    }
  });
};

const openSkhppPreviewModal = (skhpp) => {
  selectedSkhpp.value = skhpp;
  isSkhppPreviewOpen.value = true;
};

// --- LOGIKA MULTI-PAGE PDF & TTD DRAGGABLE ---
const renderPdfPage = async (pageNumber) => {
  if (!pdfDoc.value) return;
  isLoadingPdf.value = true;
  try {
    const pdfPage = await pdfDoc.value.getPage(pageNumber);
    const canvas = document.getElementById('pdf-render-canvas');
    if (!canvas) return;
    const context = canvas.getContext('2d', { alpha: false });
    const container = document.getElementById('pdf-render-container');

    const paddingOffset = typeof window !== 'undefined' && window.innerWidth < 640 ? 16 : 32;
    let availableWidth = container ? container.clientWidth - paddingOffset : 360;
    if (availableWidth < 280) {
      availableWidth = typeof window !== 'undefined' ? Math.max(280, window.innerWidth - 20) : 320;
    }
    let targetWidth = availableWidth > 800 ? 800 : availableWidth;

    const viewport = pdfPage.getViewport({ scale: targetWidth / pdfPage.getViewport({ scale: 1 }).width });

    canvas.width = viewport.width;
    canvas.height = viewport.height;

    const renderContext = {
      canvasContext: context,
      viewport: viewport,
      intent: 'print'
    };

    await pdfPage.render(renderContext).promise;
  } catch (err) {
    console.error("Render Page Error:", err);
  } finally {
    isLoadingPdf.value = false;
  }
};

const isCurrentPageActive = computed(() => {
  return pageSignatures.value && !!pageSignatures.value[currentPage.value];
});

const activePagesList = computed(() => {
  if (!pageSignatures.value) return [];
  return Object.keys(pageSignatures.value).map(Number).sort((a, b) => a - b);
});

const saveCurrentPagePos = () => {
  const canvas = document.getElementById('pdf-render-canvas');
  if (canvas && isAdjusting.value && pageSignatures.value && pageSignatures.value[currentPage.value]) {
    pageSignatures.value[currentPage.value] = {
      x: parseFloat(signaturePos.value.x / canvas.width),
      y: parseFloat(signaturePos.value.y / canvas.height),
      width: parseFloat(signatureSize.value.width / canvas.width)
    };
  }
};

const removeCurrentPageSignature = () => {
  if (pageSignatures.value && pageSignatures.value[currentPage.value]) {
    const updated = { ...pageSignatures.value };
    delete updated[currentPage.value];
    pageSignatures.value = updated;
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: `TTD Halaman ${currentPage.value} Dihapus`,
      showConfirmButton: false,
      timer: 1500
    });
  }
};

const addCurrentPageSignature = () => {
  const canvas = document.getElementById('pdf-render-canvas');
  const canvasW = canvas ? canvas.width : 500;
  const canvasH = canvas ? canvas.height : 700;

  const updated = { ...pageSignatures.value };
  updated[currentPage.value] = {
    x: 0.58,
    y: 0.72,
    width: 0.15
  };
  pageSignatures.value = updated;
  loadPagePos();
  enableDrag();
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: `TTD Ditambahkan di Halaman ${currentPage.value}`,
    showConfirmButton: false,
    timer: 1500
  });
};

const loadPagePos = () => {
  const canvas = document.getElementById('pdf-render-canvas');
  if (!canvas) return;

  const minSize = typeof window !== 'undefined' && window.innerWidth < 640 ? 55 : 60;
  if (pageSignatures.value && pageSignatures.value[currentPage.value]) {
    const pData = pageSignatures.value[currentPage.value];
    signaturePos.value = { x: pData.x * canvas.width, y: pData.y * canvas.height };
    const wPx = pData.width * canvas.width;
    signatureSize.value = { width: Math.max(minSize, wPx), height: Math.max(minSize, wPx) };
  } else {
    signaturePos.value = {
      x: Math.max(10, canvas.width * 0.58),
      y: Math.max(10, canvas.height * 0.72)
    };
    const defaultW = Math.max(minSize, canvas.width * 0.15);
    signatureSize.value = { width: defaultW, height: defaultW };
  }
};

const changePdfPage = async (delta) => {
  saveCurrentPagePos();
  const newPage = currentPage.value + delta;
  if (newPage >= 1 && newPage <= totalPages.value) {
    currentPage.value = newPage;
    await renderPdfPage(currentPage.value);
    loadPagePos();
  }
};

const goToPdfPage = async (pageNumber) => {
  if (pageNumber === currentPage.value) return;
  saveCurrentPagePos();
  if (pageNumber >= 1 && pageNumber <= totalPages.value) {
    currentPage.value = pageNumber;
    await renderPdfPage(currentPage.value);
    loadPagePos();
  }
};

const onOperatorFileSelect = async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.file = file;

  try {
    const buffer = await file.arrayBuffer();
    const loadingTask = pdfjsLib.getDocument({
      data: buffer,
      cMapUrl: `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/cmaps/`,
      cMapPacked: true
    });
    const rawPdf = await loadingTask.promise;
    pdfDoc.value = markRaw(rawPdf);
    totalPages.value = pdfDoc.value.numPages;
    currentPage.value = 1; // Default ke halaman pertama (halaman 1)
    pageSignatures.value = {};
  } catch (err) {
    console.error("Gagal membaca PDF untuk pratinjau:", err);
  }
};

const openOperatorPosPicker = async () => {
  if (!form.file) {
    Swal.fire('Perhatian', 'Harap pilih berkas PDF terlebih dahulu.', 'warning');
    return;
  }
  isModalOpen.value = false;
  isOperatorConfiguring.value = true;
  isPreviewOpen.value = true;
  isAdjusting.value = true;
  currentPage.value = 1; // Buka dari halaman 1
  await nextTick();
  await renderPdfPage(currentPage.value);

  if (!pageSignatures.value || Object.keys(pageSignatures.value).length === 0) {
    pageSignatures.value = {
      [currentPage.value]: { x: 0.58, y: 0.72, width: 0.15 }
    };
  }

  loadPagePos();
  enableDrag();
};

const saveOperatorPos = () => {
  saveCurrentPagePos();
  const canvas = document.getElementById('pdf-render-canvas');
  if (canvas) {
    form.x = parseFloat(signaturePos.value.x / canvas.width);
    form.y = parseFloat(signaturePos.value.y / canvas.height);
    form.width = parseFloat(signatureSize.value.width / canvas.width);
    form.target_page = currentPage.value;
    form.pages_data = Object.keys(pageSignatures.value).length > 0 ? JSON.stringify(pageSignatures.value) : null;
  }
  isPreviewOpen.value = false;
  isOperatorConfiguring.value = false;
  isModalOpen.value = true;
  Swal.fire({ icon: 'success', title: 'Posisi TTD Di-Set', text: `Letak TTD Komandan diset pada ${activePagesList.value.length} Halaman (${activePagesList.value.join(', ')})`, timer: 2000, showConfirmButton: false });
};

const closeOperatorPosPicker = () => {
  isPreviewOpen.value = false;
  if (isOperatorConfiguring.value) {
    isOperatorConfiguring.value = false;
    isModalOpen.value = true;
  }
};

const openPdfPreview = async (req) => {
  selectedReq.value = req;
  selectedReqId.value = req.id;
  isOperatorConfiguring.value = false;
  isPreviewOpen.value = true;
  isAdjusting.value = true;
  currentPage.value = 1; // Buka dari halaman 1 untuk Komandan
  pageSignatures.value = {};
  if (req.pages_data) {
    try {
      pageSignatures.value = typeof req.pages_data === 'string' ? JSON.parse(req.pages_data) : req.pages_data;
    } catch (e) {}
  }
  if (!pageSignatures.value || Object.keys(pageSignatures.value).length === 0) {
    pageSignatures.value = {
      [req.target_page || 1]: { x: req.x || 0.58, y: req.y || 0.72, width: req.width || 0.15 }
    };
  }
  signatureSize.value = { width: 90, height: 90 };

  await nextTick();
  try {
    isLoadingPdf.value = true;
    const response = await fetch(`${window.location.origin}/storage/${req.file_path}?t=${Date.now()}`);
    const buffer = await response.arrayBuffer();

    const loadingTask = pdfjsLib.getDocument({
      data: buffer,
      cMapUrl: `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/cmaps/`,
      cMapPacked: true,
      disableFontFace: false
    });

    const rawPdf = await loadingTask.promise;
    pdfDoc.value = markRaw(rawPdf);
    totalPages.value = pdfDoc.value.numPages;
    currentPage.value = 1; // Selalu tampilkan dari halaman 1

    await renderPdfPage(currentPage.value);
    loadPagePos();
    enableDrag();
  } catch (e) {
    Swal.fire('Error', 'File PDF tidak dapat dibaca atau diproteksi password.', 'error');
  } finally {
    isLoadingPdf.value = false;
  }
};

const enableDrag = () => {
  isAdjusting.value = true;
  if (!isCommander.value) {
    saveCurrentPagePos();
  }
  nextTick(() => {
    // Khusus Komandan: Posisi QR terkunci, tidak dapat digeser atau diubah ukurannya
    if (isCommander.value) {
      try {
        interact('.drag-signature').unset();
      } catch (e) {}
      return;
    }

    const canvas = document.getElementById('pdf-render-canvas');
    if (canvas) {
      if (signaturePos.value.x <= 50 || signaturePos.value.y <= 100) {
        signaturePos.value = {
          x: Math.max(10, canvas.width * 0.58),
          y: Math.max(10, canvas.height * 0.72)
        };
      }
    }

    interact('.drag-signature').draggable({
      inertia: false,
      modifiers: [interact.modifiers.restrictRect({ restriction: '#pdf-render-canvas', endOnly: true })],
      listeners: { 
        move(event) { 
          signaturePos.value.x += event.dx; 
          signaturePos.value.y += event.dy;
          saveCurrentPagePos();
        } 
      }
    }).resizable({
      edges: { right: true, bottom: true },
      listeners: { move(event) {
        const squareSize = Math.max(event.rect.width, event.rect.height);
        signatureSize.value.width = squareSize;
        signatureSize.value.height = squareSize;
        signaturePos.value.x += event.deltaRect.left;
        signaturePos.value.y += event.deltaRect.top;
        saveCurrentPagePos();
      }},
      modifiers: [interact.modifiers.restrictSize({ min: { width: 50, height: 50 }, max: { width: 250, height: 250 } })]
    });
  });
};

const handlePdfDecision = (status, applyAll = false) => {
  if (status === 'rejected') {
    Swal.fire({
      title: 'TOLAK PENGAJUAN BERKAS',
      text: "Tuliskan alasan penolakan atau catatan revisi untuk staf pemohon:",
      input: 'textarea',
      inputPlaceholder: 'Tuliskan alasan penolakan...',
      showCancelButton: true,
      confirmButtonText: 'KIRIM PENOLAKAN',
      confirmButtonColor: '#e11d48',
      cancelButtonText: 'BATAL',
      cancelButtonColor: '#94a3b8',
      inputValidator: (value) => { if (!value) return 'Alasan penolakan wajib diisi!' }
    }).then((result) => {
      if (result.isConfirmed) {
        decisionForm.status = 'rejected';
        decisionForm.note = result.value;
        decisionForm.patch(route('signature.update', selectedReqId.value), {
          onSuccess: () => { isPreviewOpen.value = false; Swal.fire('BERHASIL', 'Pengajuan berkas ditolak.', 'success'); }
        });
      }
    });
    return;
  }

  if (!isCommander.value) {
    saveCurrentPagePos();
  }
  const canvas = document.getElementById('pdf-render-canvas');
  const configuredCount = Object.keys(pageSignatures.value).length;
  
  decisionForm.status = 'approved';
  decisionForm.x = parseFloat(signaturePos.value.x / canvas.width); 
  decisionForm.y = parseFloat(signaturePos.value.y / canvas.height);
  decisionForm.width = parseFloat(signatureSize.value.width / canvas.width);
  decisionForm.canvas_width = canvas.width;
  decisionForm.target_page = currentPage.value;
  decisionForm.apply_to_all = applyAll;
  decisionForm.pages_data = configuredCount > 0 ? JSON.stringify(pageSignatures.value) : null;
  
  let textMsg = `Tanda Tangan Elektronik Komandan berhasil dibubuhkan pada Halaman ${currentPage.value}`;
  if (configuredCount > 1 && !applyAll) {
    textMsg = `Tanda Tangan Elektronik Komandan berhasil dibubuhkan pada ${configuredCount} Halaman Dokumen!`;
  } else if (applyAll) {
    textMsg = `Tanda Tangan Elektronik Komandan berhasil dibubuhkan pada SELURUH HALAMAN (1 s.d. ${totalPages.value})`;
  }

  decisionForm.patch(route('signature.update', selectedReqId.value), {
    onSuccess: () => { 
      isPreviewOpen.value = false; 
      Swal.fire({ icon: 'success', title: 'BERHASIL', text: textMsg, timer: 2500, showConfirmButton: false }); 
    }
  });
};

// Utilities
const downloadFile = (filePath, subject) => {
  const link = document.createElement('a');
  link.href = `/storage/${filePath}`;
  link.download = `SINDEN_${subject.replace(/\s+/g, '_')}.pdf`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const deleteRequest = (id) => {
  Swal.fire({
    title: 'Hapus Berkas?',
    text: "Data akan dihapus permanen dan validasi QR dicabut seketika!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    confirmButtonText: 'Ya, Hapus!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('signature.destroy', id), {
        onSuccess: () => Swal.fire('Terhapus', 'Berkas dan validasi berhasil dihapus.', 'success')
      });
    }
  });
};

const deleteSkhpp = (id) => {
  Swal.fire({
    title: 'HAPUS & CABUT SKHPP?',
    text: "Dokumen SKHPP dan status validasi legalitas QR akan langsung dicabut/dihapus seketika!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    confirmButtonText: 'YA, HAPUS & CABUT!',
    cancelButtonText: 'BATAL'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('skhpp.destroy', id), {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('BERHASIL DICABUT', 'SKHPP dan status validasi QR berhasil dihapus seketika.', 'success');
        }
      });
    }
  });
};

const submitRequest = () => {
  form.post(route('signature.store'), {
    onSuccess: () => { isModalOpen.value = false; form.reset(); Swal.fire('Berhasil', 'Berkas dikirim.', 'success'); },
  });
};

const getStatusClass = (status) => {
  if (status === 'approved') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  if (status === 'rejected') return 'bg-rose-50 text-rose-700 border-rose-200';
  return 'bg-amber-50 text-amber-700 border-amber-200';
};
</script>

<template>
  <Head title="Otoritas TTD Digital & SKHPP" />

  <AuthenticatedLayout>
    <div class="space-y-6 font-sans">
      
      <!-- Page Header Card -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Otoritas Validasi TTD Digital & SKHPP</h2>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
          </div>
          <p class="text-xs text-slate-500 font-semibold">Pusat Otorisasi Tanda Tangan Elektronik Komandan & Pengesahan Surat</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
          <Link href="/skhpp/create" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-emerald-500/20 transition tracking-wider text-center">
            + Pengajuan SKHPP Baru
          </Link>
          <button v-if="user.role !== 'komandan'" @click="isModalOpen = true"class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-blue-500/20 transition tracking-wider text-center">
            + Upload PDF Berkas Lain
          </button>
        </div>
      </div>

      <!-- Navigation Tabs (Mobile Scrollable) -->
      <div class="flex border-b border-slate-200 gap-2 sm:gap-4 overflow-x-auto whitespace-nowrap custom-scrollbar">
        <button 
          @click="activeTab = 'skhpp'"
          :class="activeTab === 'skhpp' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-3 sm:px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2 shrink-0"
        >
          <span>PENGAJUAN SKHPP</span>
          <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black">{{ skhppRequests?.data?.length || 0 }}</span>
        </button>

        <button 
          @click="activeTab = 'pdf'"
          :class="activeTab === 'pdf' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-3 sm:px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2 shrink-0"
        >
          <span>BERKAS DINAS / PDF LAIN</span>
          <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">{{ requests?.data?.length || 0 }}</span>
        </button>
      </div>

      <!-- TAB 1: PENGAJUAN SKHPP -->
      <div v-if="activeTab === 'skhpp'" class="bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
          <table class="w-full min-w-[760px] text-left border-collapse">
            <thead class="bg-slate-50 uppercase font-extrabold text-slate-500 text-[10px] tracking-wider border-b border-slate-100">
              <tr>
                <th class="p-4">Tanggal Pengajuan</th>
                <th class="p-4">Subjek / Nama</th>
                <th class="p-4">Pangkat / NRP / NIK</th>
                <th class="p-4">Peruntukan</th>
                <th class="p-4 text-center">Status</th>
                <th class="p-4 text-right">Aksi & Otoritas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 uppercase font-bold text-slate-800 text-[11px]">
              <tr v-for="skhpp in skhppRequests.data" :key="skhpp.id" class="hover:bg-slate-50/50 transition">
                <td class="p-4 text-slate-400 font-mono">{{ new Date(skhpp.created_at).toLocaleDateString('id-ID') }}</td>
                <td class="p-4">
                  <button 
                    type="button" 
                    @click="openSkhppPreviewModal(skhpp)" 
                    class="text-left font-black text-slate-900 hover:text-blue-600 transition block cursor-pointer">
                    {{ skhpp.nama }}
                  </button>
                  <span v-if="skhpp.kategori_personel" class="block text-[9px] text-blue-600 font-semibold">{{ skhpp.kategori_personel.toUpperCase() }}</span>
                  <div class="flex items-center gap-1.5 mt-1">
                    <button 
                      type="button" 
                      @click.stop="copyDirectLink('skhpp', skhpp)" 
                      class="inline-flex items-center gap-1 text-[9px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-2 py-0.5 rounded-lg transition cursor-pointer"
                      title="Salin Link Langsung Komandan">
                      <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                      <span>Direct Link Komandan</span>
                    </button>
                  </div>
                </td>
                <td class="p-4 text-slate-600 font-mono">{{ skhpp.pangkat_korps_nrp || skhpp.nik || '-' }}</td>
                <td class="p-4 text-slate-500 normal-case max-w-[200px] truncate" :title="skhpp.peruntukan">{{ skhpp.peruntukan }}</td>
                <td class="p-4 text-center">
                  <span :class="getStatusClass(skhpp.status)" class="px-3 py-1 rounded-full text-[9px] font-black border uppercase">
                    {{ skhpp.status === 'approved' ? 'DISETUJUI' : (skhpp.status === 'rejected' ? 'REVISI' : 'PENDING TTD') }}
                  </span>
                </td>
                <td class="p-4 text-right">
                  <div class="flex justify-end gap-2 items-center">
                    <button @click="openSkhppPreviewModal(skhpp)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase cursor-pointer"> Lihat SKHPP
                    </button>
                    
                    <button 
                      type="button" 
                      @click="copyDirectLink('skhpp', skhpp)" 
                      class="bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase flex items-center gap-1 transition cursor-pointer"
                      title="Salin Link Direct Komandan">
                      <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                      <span>Salin Link</span>
                    </button>
                    
                    <template v-if="user.role === 'admin' || user.role === 'komandan'">
                      <button v-if="skhpp.status === 'pending'" @click="approveSkhpp(skhpp)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-[9px] font-black uppercase shadow-sm cursor-pointer"> Setujui & TTD
                      </button>
                      <button v-if="skhpp.status === 'pending'" @click="rejectSkhpp(skhpp)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase cursor-pointer"> Tolak
                      </button>
                    </template>

                    <button v-if="user.role === 'admin' || user.id === skhpp.user_id" @click="deleteSkhpp(skhpp.id)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase flex items-center justify-center transition cursor-pointer" title="Hapus & Cabut Validasi QR">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!skhppRequests?.data?.length">
                <td colspan="6" class="p-8 text-center text-slate-400 italic text-xs">Belum ada permohonan SKHPP terdaftar.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Links SKHPP -->
        <div v-if="skhppRequests?.links && skhppRequests.links.length > 3" class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
          <span class="text-slate-500 font-medium">
            Menampilkan {{ skhppRequests.from || 0 }} - {{ skhppRequests.to || 0 }} dari {{ skhppRequests.total || 0 }} berkas SKHPP
          </span>
          <div class="flex items-center gap-1 flex-wrap">
            <Link 
              v-for="(lnk, lIdx) in skhppRequests.links" 
              :key="'skhpp-lnk-' + lIdx"
              :href="lnk.url ? (lnk.url + '&tab=skhpp') : '#'"
              :class="[
                lnk.active ? 'bg-blue-600 text-white font-black shadow-xs' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 font-bold',
                !lnk.url ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer'
              ]"
              class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs transition flex items-center justify-center min-w-[32px]"
              v-html="lnk.label"
            ></Link>
          </div>
        </div>
      </div>

      <!-- TAB 2: PENGAJUAN BERKAS DINAS / PDF LAIN -->
      <div v-if="activeTab === 'pdf'" class="bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
          <table class="w-full min-w-[760px] text-left border-collapse">
            <thead class="bg-slate-50 uppercase font-extrabold text-slate-500 text-[10px] tracking-wider border-b border-slate-100">
              <tr>
                <th class="p-4">Timestamp</th>
                <th class="p-4">Perihal Berkas</th>
                <th class="p-4">Pengaju</th>
                <th class="p-4 text-center">Status</th>
                <th class="p-4 text-right">Otoritas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 uppercase font-bold text-slate-800 text-[11px]">
              <tr v-for="req in requests.data" :key="req.id" class="hover:bg-slate-50/50 transition">
                <td class="p-4 text-slate-400 font-mono">{{ new Date(req.created_at).toLocaleString('id-ID') }}</td>
                <td class="p-4">
                  <button 
                    type="button" 
                    @click="openPdfPreview(req)" 
                    class="text-left font-black text-slate-900 hover:text-blue-600 transition block cursor-pointer">
                    {{ req.subject }}
                  </button>
                  <div v-if="req.note" class="text-[9px] text-rose-500 font-normal italic mt-0.5">Alasan: {{ req.note }}</div>
                  <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                    <button 
                      type="button" 
                      @click.stop="copyDirectLink('pdf', req)" 
                      class="inline-flex items-center gap-1 text-[9px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-2 py-0.5 rounded-lg transition cursor-pointer"
                      title="Salin Link Direct Komandan">
                      <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                      <span>Direct Link Komandan</span>
                    </button>
                    <a :href="'/storage/' + req.file_path" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 px-2 py-0.5 rounded-lg border border-slate-200 transition" title="Buka PDF Asli di Tab Baru">
                      <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                      <span>PDF Asli</span>
                    </a>
                  </div>
                </td>
                <td class="p-4 text-slate-600 text-xs">{{ req.user?.name || 'Operator' }}</td>
                <td class="p-4 text-center">
                  <span :class="getStatusClass(req.status)" class="px-3 py-1 rounded-full text-[9px] font-black border uppercase">
                    {{ req.status }}
                  </span>
                </td>
                <td class="p-4 text-right">
                  <div class="flex justify-end gap-2 items-center">
                    <button @click="openPdfPreview(req)" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3.5 py-1.5 rounded-xl text-[9px] font-black uppercase cursor-pointer">
                      {{ isCommander ? 'Periksa & Bubuhkan TTE' : 'Periksa & Atur TTD' }}
                    </button>
                    
                    <button 
                      type="button" 
                      @click="copyDirectLink('pdf', req)" 
                      class="bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase flex items-center gap-1 transition cursor-pointer"
                      title="Salin Link Direct Komandan">
                      <svg class="w-3 h-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                      <span>Salin Link</span>
                    </button>

                    <button v-if="req.status === 'approved'" @click="downloadFile(req.file_path, req.subject)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl text-[9px] font-black uppercase shadow-sm cursor-pointer"> Unduh
                    </button>

                    <button v-if="user.role === 'admin' || user.id === req.user_id" @click="deleteRequest(req.id)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase flex items-center justify-center transition cursor-pointer" title="Hapus Berkas">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!requests?.data?.length">
                <td colspan="5" class="p-8 text-center text-slate-400 italic text-xs">Belum ada berkas PDF diajukan.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Links PDF Berkas Lain -->
        <div v-if="requests?.links && requests.links.length > 3" class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
          <span class="text-slate-500 font-medium">
            Menampilkan {{ requests.from || 0 }} - {{ requests.to || 0 }} dari {{ requests.total || 0 }} berkas PDF
          </span>
          <div class="flex items-center gap-1 flex-wrap">
            <Link 
              v-for="(lnk, lIdx) in requests.links" 
              :key="'pdf-lnk-' + lIdx"
              :href="lnk.url ? (lnk.url + '&tab=pdf') : '#'"
              :class="[
                lnk.active ? 'bg-blue-600 text-white font-black shadow-xs' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 font-bold',
                !lnk.url ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer'
              ]"
              class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs transition flex items-center justify-center min-w-[32px]"
              v-html="lnk.label"
            ></Link>
          </div>
        </div>
      </div>

    </div>

    <!-- MODAL PRATINJAU DOKUMEN SKHPP (KOREKSI & OTORITAS KOMANDAN) -->
    <div v-if="isSkhppPreviewOpen && selectedSkhpp" class="fixed inset-0 z-[150] flex items-center justify-center p-2 sm:p-4 bg-slate-950/85 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-100 rounded-3xl max-w-4xl w-full p-3 sm:p-6 space-y-4 shadow-2xl border border-slate-300 text-left my-auto max-h-[95vh] flex flex-col">
        
        <!-- Header Bar Modal (Mobile Responsive) -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-slate-300 pb-3 gap-2.5 shrink-0">
          <div>
            <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-blue-600 block">KOREKSI & OTORITAS VALIDASI SKHPP</span>
            <h3 class="text-xs sm:text-base font-black text-slate-900 uppercase leading-tight">SKHPP: {{ selectedSkhpp.nama }}</h3>
          </div>

          <div class="flex items-center gap-1.5 flex-wrap w-full sm:w-auto">
            <button 
              type="button" 
              @click="copyDirectLink('skhpp', selectedSkhpp)" 
              class="flex-1 sm:flex-none bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-xs flex items-center justify-center gap-1 transition cursor-pointer"
              title="Salin Link Direct Dokumen Ini">
              <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
              <span>Salin Link Direct</span>
            </button>

            <Link :href="`/skhpp/${selectedSkhpp.id}/edit`" class="flex-1 sm:flex-none bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-sm text-center flex items-center justify-center gap-1"> Koreksi
            </Link>

            <template v-if="(user.role === 'admin' || user.role === 'komandan') && selectedSkhpp.status === 'pending'">
              <button @click="approveSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-md text-center flex items-center justify-center gap-1 cursor-pointer"> Setujui
              </button>
              <button @click="rejectSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-rose-600 hover:bg-rose-700 text-white px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-sm text-center flex items-center justify-center gap-1 cursor-pointer"> Tolak
              </button>
            </template>

            <button type="button" @click="isSkhppPreviewOpen = false" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase flex items-center gap-1 cursor-pointer" title="Tutup">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              <span>Tutup</span>
            </button>
          </div>
        </div>

        <!-- Scrollable Authentic SKHPP Preview Container (Mobile Responsive Scaling) -->
        <div class="flex-1 overflow-auto bg-slate-200/70 p-1.5 sm:p-6 border border-slate-300 rounded-2xl shadow-inner custom-scrollbar">
          <div class="bg-white p-4 sm:p-10 border border-slate-300 rounded-xl shadow-md font-sans text-[10pt] sm:text-[12pt] text-black leading-relaxed space-y-3 sm:space-y-4 max-w-[800px] min-w-[300px] mx-auto w-full overflow-x-auto" style="font-family: Arial, Helvetica, sans-serif;">
            
            <!-- Kop Header (Rata Tengah & Presisi Single Line) -->
            <div class="text-center max-w-[380px] w-full mx-auto text-[10pt] sm:text-[12pt] font-normal leading-snug">
                <div class="whitespace-nowrap font-normal">KOMANDO ARMADA II</div>
                <div class="font-normal text-center">DETASEMEN INTELIJEN</div>
                <div class="w-44 sm:w-48 h-[1.5px] bg-black mx-auto mt-0.5"></div>
            </div>

            <!-- Judul & Nomor SKHPP -->
            <div class="text-center my-3 sm:my-4">
                <div class="font-bold text-[10pt] sm:text-[12pt] underline uppercase tracking-tight"> SURAT KETERANGAN HASIL PENELITIAN PERSONEL
                </div>
                <div v-if="selectedSkhpp.kategori_personel === 'perusahaan'" class="font-bold text-[9pt] sm:text-[11pt] uppercase"> MITRA KERJA TNI ANGKATAN LAUT
                </div>
                <div class="text-[10pt] sm:text-[12pt] font-normal mt-0.5"> Nomor : {{ selectedSkhpp.nomor_skhpp || ('R / ' + (selectedSkhpp.nomor_urut || '....') + ' / SKHPP / ' + (selectedSkhpp.bulan_romawi || 'VIII') + ' / ' + (selectedSkhpp.tahun || '2026')) }}
                </div>
            </div>

            <!-- Poin Isi 1 s.d. 4 -->
            <div class="space-y-2 sm:space-y-3 text-[10pt] sm:text-[12pt]">
                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">1.</span>
                    <div> Dasar : {{ selectedSkhpp.surat_pengantar }}
                    </div>
                </div>

                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">2.</span>
                    <div> Dengan ini menerangkan bahwa hasil penelitian terhadap :
                    </div>
                </div>

                <!-- Sub Poin a-g (Indentasi Rata Huruf D) -->
                <div class="pl-2 sm:pl-[45px] space-y-1 text-[9pt] sm:text-[12pt]">
                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>a. Nama</span>
                        <span>:</span>
                        <span class="font-normal">{{ selectedSkhpp.nama }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>b. NIK / Pangkat</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.pangkat_korps_nrp || selectedSkhpp.nik || '-' }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>c. Jabatan / Pekerjaan</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.jabatan_pekerjaan }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>d. Tempat, tgl. lahir</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.tempat_lahir }}, {{ selectedSkhpp.tanggal_lahir }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>e. Jenis kelamin</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.jenis_kelamin }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>f. Agama</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.agama }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>g. Alamat rumah</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.alamat }}</span>
                    </div>
                </div>

                <div class="flex items-start font-normal mt-2">
                    <span class="w-5 sm:w-6 shrink-0">2.</span>
                    <div> Hasil Penelitian Personel <span class="font-bold">Memenuhi Syarat</span>
                    </div>
                </div>

                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">3.</span>
                    <div> SKHPP ini diberikan {{ selectedSkhpp.peruntukan }}
                    </div>
                </div>

                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">4.</span>
                    <div> Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut dan diadakan pembetulan seperlunya.
                    </div>
                </div>
            </div>

            <!-- TTD Block Komandan & Pas Foto 4x6 (Foto Suami-Istri Nempel & Mepet TTD, Tanpa Border) -->
            <div class="mt-4 sm:mt-6 pt-2 flex flex-col sm:flex-row justify-end items-center sm:items-end gap-1.5 sm:gap-2">
                <!-- Pas Foto Section 4x6 (Nempel Berhimpitan Tanpa Border) -->
                <div class="flex items-center gap-0 shrink-0">
                    <div v-if="selectedSkhpp.foto_1" class="w-[3.2cm] h-[4.8cm] sm:w-[4cm] sm:h-[6cm] bg-slate-100 flex items-center justify-center text-[9pt] sm:text-[10pt] text-slate-400 overflow-hidden">
                        <img :src="'/storage/' + selectedSkhpp.foto_1" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-[3.2cm] h-[4.8cm] sm:w-[4cm] sm:h-[6cm] border border-slate-300 bg-slate-100 flex items-center justify-center text-[9pt] sm:text-[10pt] text-slate-400 overflow-hidden">
                        <span>FOTO 4x6</span>
                    </div>
                    <div v-if="selectedSkhpp.is_pernikahan && selectedSkhpp.foto_2" class="w-[3.2cm] h-[4.8cm] sm:w-[4cm] sm:h-[6cm] bg-slate-100 flex items-center justify-center text-[9pt] sm:text-[10pt] text-slate-400 overflow-hidden">
                        <img :src="'/storage/' + selectedSkhpp.foto_2" class="w-full h-full object-cover" />
                    </div>
                </div>

                <!-- Block TTD Komandan -->
                <div class="w-full sm:w-[330px] text-center sm:text-left">
                    <div class="text-left">Dikeluarkan di Surabaya</div>
                    <div class="border-b border-black pb-0.5 mb-1 flex justify-between items-center text-[10pt] sm:text-[12pt]">
                        <span>pada tanggal</span>
                        <span>{{ selectedSkhpp.tanggal_skhpp ? new Date(selectedSkhpp.tanggal_skhpp).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '5 Agustus 2026' }}</span>
                    </div>
                    <div class="font-normal text-center whitespace-nowrap mt-1 leading-snug"> Komandan Detasemen Intelijen Koarmada 2,
                    </div>

                    <!-- QR Code TTD Status -->
                    <div class="my-2 py-1 text-center min-h-[85px] sm:min-h-[95px] flex items-center justify-center">
                        <div v-if="selectedSkhpp.status === 'approved'" class="text-center space-y-1">
                            <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(window.location.origin + '/verify-skhpp/' + selectedSkhpp.verification_code)}`" class="w-[75px] h-[75px] sm:w-[85px] sm:h-[85px] border border-slate-300 p-0.5 rounded-sm mx-auto block" />
                            <span class="text-[7px] sm:text-[8px] font-bold text-emerald-700 block"> TERVERIFIKASI TTD DIGITAL</span>
                        </div>
                        <div v-else class="h-[75px] sm:h-[85px] w-full flex items-center justify-center border border-dashed border-slate-300 text-[9pt] sm:text-[10px] text-slate-400 font-sans italic bg-slate-50">
                            [ PENDING TTD KOMANDAN ]
                        </div>
                    </div>

                    <div class="text-center font-normal text-[10pt] sm:text-[12pt]">
                        <div>Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                        <div>Kolonel Laut (E) NRP 16085/P</div>
                    </div>
                </div>
            </div>

            <!-- Kepada Footer -->
            <div class="pt-3 text-[10pt] sm:text-[12pt] font-normal">
                <div>Kepada :</div>
                <div class="whitespace-nowrap">Yth. Asintel Pangkoarmada 2</div>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions (Mobile Friendly) -->
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center border-t border-slate-300 pt-3 gap-2.5 shrink-0">
          <Link :href="`/skhpp/${selectedSkhpp.id}`" class="text-[11px] sm:text-xs font-bold text-blue-600 hover:underline flex items-center justify-center sm:justify-start gap-1"> Buka Halaman Cetak Lengkap (Dengan Lampiran Jika Ada) 
          </Link>
          <div class="flex gap-2 w-full sm:w-auto">
            <template v-if="(user.role === 'admin' || user.role === 'komandan') && selectedSkhpp.status === 'pending'">
              <button @click="approveSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase shadow-md text-center"> Setujui & TTD Komandan
              </button>
              <button @click="rejectSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase shadow-md text-center"> Tolak / Minta Revisi
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL MULTI-PAGE PDF PREVIEW & ATUR TTD -->
    <div v-if="isPreviewOpen" class="fixed inset-0 z-[150] flex flex-col bg-slate-950 animate-in fade-in duration-300">
      
      <!-- Top Navigation & Actions Bar (Mobile Responsive) -->
      <div class="bg-white border-b px-2.5 py-2 sm:px-4 sm:py-3 flex items-center justify-between shadow-xl shrink-0 gap-2">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
          <span class="text-blue-600 text-[10px] font-black uppercase tracking-widest hidden md:inline shrink-0">VERIFIKASI PDF</span>
          
          <!-- Page Controls (Halaman Lebih Dari Satu) -->
          <div v-if="totalPages > 1" class="flex items-center gap-1 sm:gap-2 bg-slate-100 p-0.5 sm:p-1 rounded-xl sm:rounded-2xl border border-slate-200 text-xs shadow-inner shrink-0">
            <!-- Tombol Halaman Sebelumnya -->
            <button 
              type="button"
              @click="changePdfPage(-1)" 
              :disabled="currentPage <= 1" 
              class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg sm:rounded-xl bg-white font-black text-slate-700 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-200 border border-slate-200 flex items-center gap-1 transition shadow-xs cursor-pointer text-[11px] sm:text-xs"
              title="Ke Halaman Sebelumnya">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
              <span class="hidden sm:inline">Sebelumnya</span>
            </button>

            <!-- Indikator Halaman -->
            <span class="font-extrabold text-slate-800 px-1 whitespace-nowrap text-[11px] sm:text-xs">
              <span class="hidden xs:inline">Hal </span><span class="text-blue-600 text-xs sm:text-sm font-black">{{ currentPage }}</span> / {{ totalPages }}
            </span>

            <!-- Tombol Halaman Berikutnya (Menuju Halaman 2, 3, dst) -->
            <button 
              type="button"
              @click="changePdfPage(1)" 
              :disabled="currentPage >= totalPages" 
              class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg sm:rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700 disabled:bg-white disabled:text-slate-400 disabled:border-slate-200 disabled:opacity-30 disabled:cursor-not-allowed border border-blue-600 flex items-center gap-1 transition shadow-xs cursor-pointer text-[11px] sm:text-xs"
              title="Ke Halaman Berikutnya">
              <span class="hidden sm:inline">Berikutnya</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Pilihan Cepat Nomor Halaman Langsung (Hanya Layar Besar) -->
            <div class="hidden lg:flex items-center gap-1 border-l border-slate-300 pl-1.5 ml-1">
              <button 
                v-for="p in totalPages" 
                :key="'p-btn-' + p"
                type="button"
                @click="goToPdfPage(p)"
                :class="currentPage === p ? 'bg-blue-600 text-white font-black shadow-xs ring-2 ring-blue-300' : 'bg-white text-slate-700 hover:bg-slate-200 font-bold border border-slate-200'"
                class="px-2 py-1 rounded-lg text-xs transition cursor-pointer flex items-center gap-1">
                <span>Hal {{ p }}</span>
                <span v-if="pageSignatures && pageSignatures[p]" class="w-1.5 h-1.5 rounded-full bg-emerald-400" title="Ada TTD"></span>
              </button>
            </div>

            <!-- Badge TTD Terpasang -->
            <span v-if="activePagesList.length > 0" class="hidden sm:flex ml-1 px-2.5 py-1 bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase shadow-xs items-center gap-1 shrink-0">
              <svg class="w-3 h-3 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              <span>TTD di {{ activePagesList.length }} Hal</span>
            </span>
          </div>
        </div>

        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <button 
            v-if="selectedReq" 
            type="button" 
            @click="copyDirectLink('pdf', selectedReq)" 
            class="bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-2.5 py-1.5 sm:px-3 sm:py-2 rounded-xl text-[11px] sm:text-xs font-black uppercase flex items-center gap-1 transition cursor-pointer"
            title="Salin Link Direct Dokumen Ini">
            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            <span class="hidden sm:inline">Salin Link Direct</span>
            <span class="sm:hidden text-[10px]">Link</span>
          </button>

          <!-- Opsi atur TTD per halaman hanya untuk selain Komandan -->
          <template v-if="!isCommander">
            <button v-if="isAdjusting && isCurrentPageActive" @click="removeCurrentPageSignature" class="bg-rose-600 hover:bg-rose-700 text-white px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-xl text-[11px] sm:text-xs font-black uppercase shadow-md whitespace-nowrap"> 
              <span class="hidden sm:inline">Hapus TTD di Hal. {{ currentPage }}</span>
              <span class="sm:hidden">- TTD</span>
            </button>
            <button v-else-if="isAdjusting && !isCurrentPageActive" @click="addCurrentPageSignature" class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-xl text-[11px] sm:text-xs font-black uppercase shadow-md whitespace-nowrap">
              <span class="hidden sm:inline">+ Pasang TTD di Hal. {{ currentPage }}</span>
              <span class="sm:hidden">+ TTD</span>
            </button>
          </template>

          <button type="button" @click="closeOperatorPosPicker" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-2.5 py-1.5 sm:px-3 sm:py-2 rounded-xl text-[11px] sm:text-xs font-black uppercase border border-rose-200 flex items-center gap-1 transition cursor-pointer" title="Tutup Pratinjau">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span class="hidden sm:inline">Tutup</span>
          </button>
        </div>
      </div>

      <!-- Canvas Render Area (Touch-Friendly & Auto-Centered) -->
      <div class="flex-1 relative overflow-auto bg-slate-900/60 p-2 sm:p-4 custom-scrollbar flex justify-center items-start touch-pan-x touch-pan-y" id="pdf-render-container">
        <div v-if="isLoadingPdf" class="absolute inset-0 z-[200] flex items-center justify-center bg-slate-950/80">
          <div class="animate-spin border-4 border-blue-500 border-t-transparent rounded-full w-10 h-10"></div>
        </div>

        <div class="relative bg-white shadow-2xl overflow-hidden rounded-sm my-auto sm:my-0" style="line-height: 0;">
          <canvas id="pdf-render-canvas" class="block max-w-full h-auto"></canvas>

          <!-- Signature Box (Khusus Komandan: Posisi Terkunci / Read-Only; Operator: Draggable & Resizable) -->
          <div v-if="isAdjusting && isCurrentPageActive" 
               :class="isCommander 
                 ? 'border-2 border-emerald-600 bg-white/95 shadow-xl select-none cursor-default' 
                 : 'drag-signature cursor-move border-2 border-blue-600 bg-white shadow-2xl touch-none'"
               class="absolute z-[200] flex items-center justify-center text-center p-0.5"
               :style="{ left: signaturePos.x + 'px', top: signaturePos.y + 'px', width: signatureSize.width + 'px', height: signatureSize.height + 'px' }">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=SINDEN_PREVIEW" class="w-full h-full object-contain pointer-events-none" alt="QR Code TTD Digital" />
            
            <!-- Resize handle hanya untuk Operator / Non-Komandan -->
            <div v-if="!isCommander" class="absolute -bottom-2 -right-2 w-6 h-6 bg-blue-600 rounded-full border-2 border-white shadow-lg cursor-se-resize flex items-center justify-center">
              <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
            </div>

            <!-- Badge Posisi TTE Digital untuk Komandan -->
            <div v-if="isCommander" class="absolute -top-6 left-1/2 -translate-x-1/2 bg-emerald-600 text-white text-[8px] font-black uppercase px-2 py-0.5 rounded shadow-sm whitespace-nowrap flex items-center gap-1 pointer-events-none">
              <svg class="w-2.5 h-2.5 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              <span>POSISI TTE DIGITAL</span>
            </div>
          </div>

          <!-- Overlay Halaman Tanpa TTD -->
          <div v-else-if="isAdjusting && !isCurrentPageActive" class="absolute inset-0 bg-slate-950/30 backdrop-blur-[1px] flex flex-col items-center justify-center gap-3 z-[100] text-center p-4">
            <div class="bg-white/95 p-4 sm:p-5 rounded-2xl shadow-2xl border border-slate-200 max-w-xs space-y-2">
              <span class="text-xs font-black uppercase text-slate-500 block">Halaman {{ currentPage }} Tanpa TTD</span>
              <p class="text-[10px] text-slate-600 font-medium">Halaman ini dibuat tanpa tanda tangan Komandan.</p>
              <button v-if="!isCommander" type="button" @click="addCurrentPageSignature" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-xl text-xs font-black uppercase shadow-md">
                + Pasang TTD di Halaman {{ currentPage }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Banner Petunjuk Multi-Halaman (Mobile Optimized) -->
      <div v-if="totalPages > 1" class="bg-blue-50 border-t border-b border-blue-100 px-3 py-2 text-center text-xs font-bold text-blue-900 shrink-0 flex items-center justify-between sm:justify-center gap-2 flex-wrap sm:flex-nowrap">
        <span class="text-[11px] sm:text-xs text-blue-800 font-bold whitespace-nowrap">Hal Target:</span>
        <div class="flex items-center gap-1.5 overflow-x-auto max-w-full pb-0.5 custom-scrollbar">
          <button 
            v-for="p in totalPages" 
            :key="'bottom-p-' + p"
            type="button"
            @click="goToPdfPage(p)"
            :class="currentPage === p ? 'bg-blue-600 text-white font-black shadow-xs ring-2 ring-blue-300' : 'bg-white text-blue-900 border border-blue-200 hover:bg-blue-100 font-bold'"
            class="px-2.5 py-1 rounded-lg text-xs transition cursor-pointer flex items-center gap-1 shadow-2xs whitespace-nowrap shrink-0">
            <span>Hal {{ p }}</span>
            <span v-if="pageSignatures && pageSignatures[p]" class="px-1.5 py-0.2 bg-emerald-600 text-white text-[8px] sm:text-[9px] rounded font-black uppercase">TTD</span>
          </button>
        </div>
      </div>

      <!-- Bottom Bar untuk Operator (Save Position Only) -->
      <div v-if="isOperatorConfiguring" class="bg-white border-t p-3 sm:p-4 flex flex-col sm:flex-row justify-center gap-2 sm:gap-3 shrink-0 shadow-2xl">
        <button type="button" @click="saveOperatorPos" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-xs uppercase shadow-lg text-center">
          SIMPAN POSISI TTD ( {{ activePagesList.length }} HALAMAN )
        </button>
        <button type="button" @click="closeOperatorPosPicker" class="w-full sm:w-auto bg-slate-100 text-slate-600 px-6 py-2.5 sm:py-3 rounded-xl sm:rounded-2xl font-black text-xs uppercase border border-slate-200 text-center">
          BATAL
        </button>
      </div>

      <!-- Bottom Bar Khusus Komandan: Hanya Bubuhkan TTE / Tolak dengan Alasan (Mobile Friendly) -->
      <div v-else-if="isCommander" class="bg-white border-t p-3 sm:px-6 sm:py-4 flex flex-col sm:flex-row justify-center items-stretch sm:items-center gap-2 sm:gap-4 shrink-0 shadow-2xl">
        <button 
          type="button"
          @click="handlePdfDecision('approved', false)" 
          :disabled="decisionForm.processing" 
          class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white px-8 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-xs uppercase shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition cursor-pointer"
          title="Bubuhkan Tanda Tangan Elektronik Komandan">
          <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          <span>BUBUHKAN TTE</span>
        </button>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <button 
            type="button"
            @click="handlePdfDecision('rejected')" 
            :disabled="decisionForm.processing" 
            class="flex-1 sm:flex-initial bg-rose-600 hover:bg-rose-700 active:scale-95 text-white px-5 sm:px-7 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-[11px] sm:text-xs uppercase shadow-lg shadow-rose-600/30 flex items-center justify-center gap-1.5 transition cursor-pointer text-center"
            title="Tolak Pengajuan Dokumen dengan Alasan">
            <svg class="w-3.5 h-3.5 text-rose-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>TOLAK DENGAN ALASAN</span>
          </button>

          <button 
            type="button" 
            @click="isPreviewOpen = false" 
            class="flex-1 sm:flex-initial bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 sm:px-6 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-xs uppercase border border-slate-200 transition cursor-pointer text-center">
            BATAL
          </button>
        </div>
      </div>

      <!-- Bottom Confirm / Reject Bar untuk Admin (Mobile Friendly) -->
      <div v-else-if="user.role === 'admin'" class="bg-white border-t p-3 sm:p-4 flex flex-wrap justify-center items-center gap-2 sm:gap-3 shrink-0 shadow-2xl">
        <button v-if="isAdjusting" @click="handlePdfDecision('approved', false)" :disabled="decisionForm.processing" class="flex-1 sm:flex-initial bg-emerald-600 hover:bg-emerald-700 text-white px-4 sm:px-6 py-2.5 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase shadow-lg text-center"> SETUJUI ({{ activePagesList.length }} HAL)
        </button>
        <button v-if="isAdjusting && totalPages > 1" @click="handlePdfDecision('approved', true)" :disabled="decisionForm.processing" class="flex-1 sm:flex-initial bg-indigo-600 hover:bg-indigo-700 text-white px-4 sm:px-6 py-2.5 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase shadow-lg text-center"> SEMUA HAL (1-{{ totalPages }})
        </button>
        <button @click="handlePdfDecision('rejected')" :disabled="decisionForm.processing" class="flex-1 sm:flex-initial bg-rose-600 hover:bg-rose-700 text-white px-4 sm:px-5 py-2.5 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase shadow-lg text-center"> TOLAK / REVISI
        </button>
        <button v-if="isAdjusting" @click="isPreviewOpen = false" class="flex-1 sm:flex-initial bg-slate-100 text-slate-600 px-4 sm:px-5 py-2.5 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase border border-slate-200 text-center"> BATAL
        </button>
      </div>

    </div>

    <!-- MODAL REGISTRASI BERKAS PDF BARU (LENGKAP SESUAI LAYOUT VERIFIKASI) -->
    <transition name="modal-pop">
      <div v-show="isModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-4 bg-slate-950/75 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white w-full max-w-xl rounded-3xl p-6 sm:p-7 shadow-2xl border border-slate-100 space-y-5 text-left my-auto max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between border-b pb-3 shrink-0">
            <div>
              <span class="text-[9px] font-black text-blue-600 uppercase tracking-widest block">REGISTRASI BERKAS & METADATA PUBLIC VERIFY</span>
              <h3 class="font-black text-sm sm:text-base text-slate-900 uppercase leading-tight">Unggah Berkas Dinas (PDF)</h3>
            </div>
            <button type="button" @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold p-1 text-2xl leading-none transition cursor-pointer" title="Tutup">&times;</button>
          </div>

          <form @submit.prevent="submitRequest" class="space-y-4 overflow-y-auto custom-scrollbar pr-1 flex-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Judul / Jenis Naskah Dinas</label>
                <input v-model="form.document_title" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="SURAT PERINTAH / NOTA DINAS..." required>
              </div>

              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Nomor Surat Resmi / Agenda (Opsional)</label>
                <input v-model="form.letter_number" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Sprin / 15 / VIII / 2026...">
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Perihal / Subjek Surat</label>
              <input v-model="form.subject" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Perihal Penugasan / Pengamanan Sektor..." required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Nama Subjek / Personel (Opsional)</label>
                <input v-model="form.person_name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" :placeholder="user?.name || 'Nama Personel...'">
              </div>

              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Pangkat / Korps / NRP / NIK (Opsional)</label>
                <input v-model="form.pangkat_nrp" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" :placeholder="(user?.pangkat || 'TNI AL') + ' / NRP ' + (user?.nrp || '12345')">
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Jabatan / Pekerjaan (Opsional)</label>
              <input v-model="form.jabatan" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Personel Denintel Koarmada 2...">
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Maksud & Peruntukan / Keperluan (Opsional)</label>
              <textarea v-model="form.peruntukan" rows="2" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Maksud & Keperluan Penerbitan Dokumen Resmi..."></textarea>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Unggah Berkas PDF (Dapat Lebih Dari 1 Halaman)</label>
              <input type="file" @change="onOperatorFileSelect" accept=".pdf" class="w-full text-xs text-slate-500 border border-slate-200 rounded-xl p-2 bg-slate-50" :required="!form.file" />
              <div v-if="form.file" class="mt-2 text-xs font-bold text-emerald-800 bg-emerald-50 p-2.5 rounded-xl border border-emerald-200 flex items-center justify-between">
                <span>Berkas Terpilih: <strong>{{ form.file.name }}</strong> ({{ (form.file.size / 1024 / 1024).toFixed(2) }} MB)</span>
                <span class="text-[9px] bg-emerald-600 text-white px-2.5 py-0.5 rounded-full font-black uppercase">TERLAMPIR SIAP DIKIRIM</span>
              </div>
            </div>

            <!-- Tombol Penentuan Posisi TTD Komandan oleh Operator -->
            <div v-if="form.file" class="bg-blue-50/80 p-3.5 rounded-2xl border border-blue-200 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-900">ATUR POSISI LOKASI TTD KOMANDAN</span>
                <span v-if="form.target_page" class="text-[9px] font-extrabold bg-blue-600 text-white px-2 py-0.5 rounded-full">Hal. {{ form.target_page }} Di-Set</span>
              </div>
              <p class="text-[10px] font-semibold text-slate-600">Geser & tandai posisi QR Code TTD Komandan pada lembar PDF ini agar Komandan dapat langsung melihat & menyetujui.</p>
              <button type="button" @click="openOperatorPosPicker" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-xl text-xs font-black uppercase shadow-md flex items-center justify-center gap-2">
                <span>Tandai / Atur Posisi TTD Komandan (Preview PDF)</span>
              </button>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t shrink-0">
              <button type="button" @click="isModalOpen = false" class="bg-slate-100 text-slate-600 px-4 py-2.5 rounded-xl text-xs font-bold uppercase">Batal</button>
              <button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-xs font-black uppercase shadow-md">Kirim Berkas</button>
            </div>
          </form>
        </div>
      </div>
    </transition>

  </AuthenticatedLayout>
</template>

<style scoped>
#pdf-render-canvas { display: block; margin: 0 auto; background-color: #fff; }
.drag-signature { touch-action: none; user-select: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
</style>