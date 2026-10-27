package id.my.sisinden.app.ui.pdf

import id.my.sisinden.app.core.security.expectExternalActivity
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.calculatePan
import androidx.compose.foundation.gestures.calculateZoom
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.FileDownload
import androidx.compose.material.icons.outlined.OpenInNew
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material.icons.outlined.ZoomIn
import androidx.compose.material.icons.outlined.ZoomOut
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.SnackbarResult
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.input.pointer.positionChanged
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.core.ui.components.ErrorState
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

sealed interface PdfUiState {
    data object Downloading : PdfUiState
    data class Ready(val file: File, val document: PdfDocument) : PdfUiState
    data class Failed(val error: ApiError) : PdfUiState
}

class PdfViewModel(
    private val repository: SindenRepository,
    private val apiPath: String,
    private val fileName: String,
) : ViewModel() {
    private val _state = MutableStateFlow<PdfUiState>(PdfUiState.Downloading)
    val state: StateFlow<PdfUiState> = _state.asStateFlow()

    init { load() }

    fun load() {
        (_state.value as? PdfUiState.Ready)?.document?.close()
        _state.value = PdfUiState.Downloading
        viewModelScope.launch {
            when (val res = repository.downloadPdf(apiPath, fileName)) {
                is ApiResult.Success -> {
                    _state.value = try {
                        PdfUiState.Ready(res.data, PdfDocument.open(res.data))
                    } catch (e: Exception) {
                        PdfUiState.Failed(ApiError(ErrorKind.SERVER, "Dokumen PDF tidak dapat dibuka (berkas rusak atau terenkripsi)."))
                    }
                }
                is ApiResult.Failure -> _state.value = PdfUiState.Failed(res.error)
            }
        }
    }

    override fun onCleared() {
        (_state.value as? PdfUiState.Ready)?.document?.close()
    }
}

private fun shareUri(context: Context, file: File): Uri =
    FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)

@Composable
fun PdfPreviewScreen(
    repository: SindenRepository,
    apiPath: String,
    title: String,
    fileName: String,
    onBack: () -> Unit,
    autoSave: Boolean = false,
) {
    val context = LocalContext.current
    val vm: PdfViewModel = viewModel(key = "pdf-$apiPath") { PdfViewModel(repository, apiPath, fileName) }
    val state by vm.state.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    val ready = state as? PdfUiState.Ready

    // Simpan ke perangkat lewat Storage Access Framework (tanpa izin penyimpanan luas).
    val saveLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("application/pdf")) { uri ->
        val file = ready?.file
        if (uri == null || file == null) return@rememberLauncherForActivityResult
        scope.launch {
            val ok = withContext(Dispatchers.IO) {
                runCatching {
                    context.contentResolver.openOutputStream(uri)?.use { out -> file.inputStream().use { it.copyTo(out) } } != null
                }.getOrDefault(false)
            }
            if (ok) {
                val result = snackbar.showSnackbar("PDF tersimpan di perangkat.", actionLabel = "Buka", duration = SnackbarDuration.Long)
                if (result == SnackbarResult.ActionPerformed) openWithExternalApp(context, uri, snackbar, scope)
            } else {
                snackbar.showSnackbar("Gagal menyimpan PDF. Coba pilih lokasi lain.")
            }
        }
    }

    // Dibuka dari tombol "Unduh PDF": langsung tawarkan lokasi simpan setelah dokumen siap.
    var autoSaveDone by rememberSaveable { mutableStateOf(false) }
    LaunchedEffect(ready != null) {
        if (autoSave && ready != null && !autoSaveDone) {
            autoSaveDone = true
            run { context.expectExternalActivity(); saveLauncher.launch(fileName.ifBlank { "dokumen.pdf" }) }
        }
    }

    SindenScaffold(
        title = title.ifBlank { "Dokumen PDF" },
        subtitle = ready?.let { "${it.document.pageCount} halaman" } ?: "Pratinjau dokumen resmi",
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            if (ready != null) {
                IconButton(onClick = { run { context.expectExternalActivity(); saveLauncher.launch(fileName.ifBlank { "dokumen.pdf" }) } }) {
                    Icon(Icons.Outlined.FileDownload, contentDescription = "Simpan ke perangkat")
                }
                IconButton(onClick = {
                    val uri = shareUri(context, ready.file)
                    val intent = Intent(Intent.ACTION_SEND).apply {
                        type = "application/pdf"
                        putExtra(Intent.EXTRA_STREAM, uri)
                        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    }
                    context.expectExternalActivity()
                    context.startActivity(Intent.createChooser(intent, "Bagikan PDF"))
                }) {
                    Icon(Icons.Outlined.Share, contentDescription = "Bagikan")
                }
                IconButton(onClick = { openWithExternalApp(context, shareUri(context, ready.file), snackbar, scope) }) {
                    Icon(Icons.Outlined.OpenInNew, contentDescription = "Buka dengan aplikasi lain")
                }
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (val s = state) {
                is PdfUiState.Downloading -> Column(
                    Modifier.align(Alignment.Center),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    CircularProgressIndicator()
                    Spacer(Modifier.height(Spacing.md))
                    Text("Menyiapkan dokumen dari server…", style = MaterialTheme.typography.bodyMedium)
                }
                is PdfUiState.Failed -> ErrorState(s.error, onRetry = vm::load, modifier = Modifier.align(Alignment.Center))
                is PdfUiState.Ready -> PdfPager(s.document)
            }
        }
    }
}

private fun openWithExternalApp(
    context: Context,
    uri: Uri,
    snackbar: SnackbarHostState,
    scope: kotlinx.coroutines.CoroutineScope,
) {
    val intent = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, "application/pdf")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    try {
        context.expectExternalActivity()
        context.startActivity(intent)
    } catch (_: ActivityNotFoundException) {
        scope.launch { snackbar.showSnackbar("Tidak ada aplikasi pembaca PDF di perangkat.") }
    }
}

@Composable
private fun PdfPager(document: PdfDocument) {
    val listState = rememberLazyListState()
    val scope = rememberCoroutineScope()
    var scale by remember { mutableFloatStateOf(1f) }
    var offsetX by remember { mutableFloatStateOf(0f) }
    val currentPage by remember { derivedStateOf { listState.firstVisibleItemIndex + 1 } }

    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.surfaceVariant)) {
        BoxWithConstraints(Modifier.weight(1f).fillMaxWidth().clipToBounds()) {
            val density = LocalDensity.current
            val pageWidthPx = with(density) { (maxWidth - 24.dp).roundToPx() }
            val maxOffset = with(density) { maxWidth.toPx() } * (scale - 1f) / 2f

            LazyColumn(
                state = listState,
                modifier = Modifier
                    .fillMaxSize()
                    .pointerInput(Unit) {
                        // Cubit untuk zoom (2 jari); geser horizontal saat diperbesar.
                        awaitEachGesture {
                            awaitFirstDown(requireUnconsumed = false)
                            do {
                                val event = awaitPointerEvent()
                                if (event.changes.size >= 2) {
                                    scale = (scale * event.calculateZoom()).coerceIn(1f, 4f)
                                    event.changes.forEach { if (it.positionChanged()) it.consume() }
                                }
                                val pan = event.calculatePan()
                                if (scale > 1f && event.changes.none { it.isConsumed }) {
                                    offsetX += pan.x
                                }
                            } while (event.changes.any { it.pressed })
                        }
                    }
                    .pointerInput(Unit) {
                        detectTapGestures(onDoubleTap = {
                            if (scale > 1f) { scale = 1f; offsetX = 0f } else scale = 2f
                        })
                    }
                    .graphicsLayer {
                        scaleX = scale
                        scaleY = scale
                        translationX = offsetX.coerceIn(-maxOffset, maxOffset)
                        transformOrigin = TransformOrigin(0.5f, 0f)
                    },
                contentPadding = PaddingValues(12.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                items(document.pageCount) { index ->
                    PdfPage(document, index, (pageWidthPx * 2).coerceAtMost(2400))
                }
            }
        }

        // Bilah navigasi halaman & zoom
        Surface(color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = Spacing.sm, vertical = Spacing.xs),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(
                    onClick = { scope.launch { listState.animateScrollToItem((currentPage - 2).coerceAtLeast(0)) } },
                    enabled = currentPage > 1,
                ) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = "Halaman sebelumnya") }
                Text(
                    "Hal. $currentPage / ${document.pageCount}",
                    style = MaterialTheme.typography.labelLarge,
                    modifier = Modifier.weight(1f),
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                )
                IconButton(
                    onClick = { scope.launch { listState.animateScrollToItem(currentPage.coerceAtMost(document.pageCount - 1)) } },
                    enabled = currentPage < document.pageCount,
                ) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = "Halaman berikutnya") }
                Spacer(Modifier.width(Spacing.sm))
                IconButton(onClick = {
                    scale = (scale - 0.5f).coerceAtLeast(1f)
                    if (scale == 1f) offsetX = 0f
                }, enabled = scale > 1f) { Icon(Icons.Outlined.ZoomOut, contentDescription = "Perkecil") }
                Text("${(scale * 100).toInt()}%", style = MaterialTheme.typography.labelMedium)
                IconButton(onClick = { scale = (scale + 0.5f).coerceAtMost(4f) }, enabled = scale < 4f) {
                    Icon(Icons.Outlined.ZoomIn, contentDescription = "Perbesar")
                }
            }
        }
    }
}

@Composable
private fun PdfPage(document: PdfDocument, index: Int, renderWidthPx: Int) {
    val bitmap by produceState<Bitmap?>(initialValue = null, document, index, renderWidthPx) {
        value = document.render(index, renderWidthPx)
    }
    Box(
        Modifier
            .fillMaxWidth()
            .aspectRatio(1f / document.pageAspectRatios[index])
            .shadow(2.dp)
            .background(androidx.compose.ui.graphics.Color.White),
        contentAlignment = Alignment.Center,
    ) {
        val bmp = bitmap
        if (bmp == null) {
            CircularProgressIndicator(Modifier.size(28.dp), strokeWidth = 2.dp)
        } else {
            Image(
                bitmap = bmp.asImageBitmap(),
                contentDescription = "Halaman ${index + 1}",
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.FillBounds,
            )
        }
    }
}
