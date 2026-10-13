package id.my.sisinden.app.ui.skhpp

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AddAPhoto
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.PersonAdd
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.DateField
import id.my.sisinden.app.core.ui.components.DropdownField
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.SkhppMemberInput
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.data.repository.UploadImage
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.UiMessages
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream

/** Foto terpilih yang sudah dinormalisasi ke JPEG (maks. sisi 2048 px). */
class PickedPhoto(val image: UploadImage, val preview: Bitmap)

/** Baca & normalisasi foto ke JPEG agar lolos validasi server (jpeg/png, ≤10 MB). */
private suspend fun loadPhoto(context: Context, uri: Uri, name: String): PickedPhoto? = withContext(Dispatchers.IO) {
    runCatching {
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
        var sample = 1
        while (bounds.outWidth / sample > 2048 || bounds.outHeight / sample > 2048) sample *= 2
        val opts = BitmapFactory.Options().apply { inSampleSize = sample }
        val bitmap = context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, opts) }
            ?: return@runCatching null
        val out = ByteArrayOutputStream()
        bitmap.compress(Bitmap.CompressFormat.JPEG, 88, out)
        val bytes = out.toByteArray()
        if (bytes.size > 10 * 1024 * 1024) return@runCatching null
        PickedPhoto(UploadImage("$name.jpg", "image/jpeg", bytes), bitmap)
    }.getOrNull()
}

class SkhppFormViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _submitting = MutableStateFlow(false)
    val submitting: StateFlow<Boolean> = _submitting.asStateFlow()
    val messages = UiMessages()

    fun submit(fields: Map<String, String>, members: List<SkhppMemberInput>, photo1: PickedPhoto, photo2: PickedPhoto?, onDone: () -> Unit) {
        if (_submitting.value) return
        _submitting.value = true
        viewModelScope.launch {
            when (val res = repository.createSkhpp(fields, members, photo1.image, photo2?.image)) {
                is ApiResult.Success -> onDone()
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _submitting.value = false
        }
    }
}

private val KATEGORI = listOf("militer" to "Militer", "sipil_dinas" to "Sipil Dinas (PNS)", "perusahaan" to "Mitra Kerja / Perusahaan")
private val AGAMA = listOf("Islam", "Kristen Protestan", "Katolik", "Hindu", "Buddha", "Konghucu")

@Composable
fun SkhppFormScreen(repository: SindenRepository, onBack: () -> Unit, onSubmitted: () -> Unit) {
    val context = LocalContext.current
    val vm: SkhppFormViewModel = viewModel { SkhppFormViewModel(repository) }
    val submitting by vm.submitting.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    CollectMessages(vm.messages, snackbar)

    var kategori by rememberSaveable { mutableStateOf("militer") }
    var pernikahan by rememberSaveable { mutableStateOf(false) }
    var suratPengantar by rememberSaveable { mutableStateOf("") }
    var nama by rememberSaveable { mutableStateOf("") }
    var pangkatNrp by rememberSaveable { mutableStateOf("") }
    var nik by rememberSaveable { mutableStateOf("") }
    var jabatan by rememberSaveable { mutableStateOf("") }
    var tempatLahir by rememberSaveable { mutableStateOf("") }
    var tanggalLahir by rememberSaveable { mutableStateOf("") }
    var jenisKelamin by rememberSaveable { mutableStateOf("Laki-laki") }
    var agama by rememberSaveable { mutableStateOf("") }
    var alamat by rememberSaveable { mutableStateOf("") }
    var peruntukan by rememberSaveable { mutableStateOf("") }
    var photo1 by remember { mutableStateOf<PickedPhoto?>(null) }
    var photo2 by remember { mutableStateOf<PickedPhoto?>(null) }
    val members = remember { mutableStateListOf<SkhppMemberInput>() }
    var errors by remember { mutableStateOf(emptyMap<String, String>()) }

    var pickingSecond by remember { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) {
            val second = pickingSecond
            scope.launch {
                val p = loadPhoto(context, uri, if (second) "foto_2" else "foto_1")
                if (p == null) {
                    snackbar.showSnackbar("Foto tidak dapat dibaca atau melebihi 10 MB.")
                } else if (second) photo2 = p else photo1 = p
            }
        }
    }
    fun pick(second: Boolean) {
        pickingSecond = second
        picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
    }

    SindenScaffold(title = "Pengajuan SKHPP", subtitle = "Data mengikuti formulir web SISINDEN", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).imePadding().padding(horizontal = Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.xs),
        ) {
            SectionTitle("Kategori")
            DropdownField(
                label = "Kategori personel",
                options = KATEGORI,
                selected = KATEGORI.firstOrNull { it.first == kategori },
                optionLabel = { it.second },
                onSelect = { kategori = it.first },
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Keperluan pernikahan", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                Switch(checked = pernikahan, onCheckedChange = { pernikahan = it })
            }
            SindenTextField(suratPengantar, { suratPengantar = it }, "Surat pengantar / dasar", error = errors["surat_pengantar"], singleLine = false, minLines = 2)

            SectionTitle("Data personel")
            SindenTextField(nama, { nama = it }, "Nama lengkap", error = errors["nama"])
            if (kategori == "militer") {
                SindenTextField(pangkatNrp, { pangkatNrp = it }, "Pangkat / korps / NRP", error = errors["pangkat_korps_nrp"])
            } else {
                SindenTextField(nik, { nik = it.filter { c -> c.isDigit() }.take(16) }, "NIK", error = errors["nik"], keyboardType = androidx.compose.ui.text.input.KeyboardType.Number)
            }
            SindenTextField(jabatan, { jabatan = it }, "Jabatan / pekerjaan", error = errors["jabatan_pekerjaan"])
            SindenTextField(tempatLahir, { tempatLahir = it }, "Tempat lahir", error = errors["tempat_lahir"])
            DateField(tanggalLahir, { tanggalLahir = it }, "Tanggal lahir", error = errors["tanggal_lahir"])
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                listOf("Laki-laki", "Perempuan").forEach { jk ->
                    FilterChip(selected = jenisKelamin == jk, onClick = { jenisKelamin = jk }, label = { Text(jk) })
                }
            }
            DropdownField(
                label = "Agama",
                options = AGAMA,
                selected = agama.takeIf { it.isNotBlank() },
                optionLabel = { it },
                onSelect = { agama = it },
                error = errors["agama"],
            )
            SindenTextField(alamat, { alamat = it }, "Alamat", error = errors["alamat"], singleLine = false, minLines = 2)
            SindenTextField(peruntukan, { peruntukan = it }, "Peruntukan SKHPP", error = errors["peruntukan"], singleLine = false, minLines = 2)

            SectionTitle(if (pernikahan) "Foto calon suami & istri" else "Foto personel")
            PhotoSlot(if (pernikahan) "Foto calon suami" else "Foto utama", photo1, errors["foto_1"], onPick = { pick(false) }, onClear = { photo1 = null })
            if (pernikahan) {
                PhotoSlot("Foto calon istri", photo2, errors["foto_2"], onPick = { pick(true) }, onClear = { photo2 = null })
            }

            SectionTitle("Pengikut (opsional)")
            members.forEachIndexed { index, m ->
                SindenCard(Modifier.fillMaxWidth(), contentPadding = Spacing.md) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text("${index + 1}. ${m.nama}", style = MaterialTheme.typography.titleSmall)
                            Text("${m.pangkatNrpNik} · ${m.jabatan}", style = MaterialTheme.typography.bodySmall)
                        }
                        IconButton(onClick = { members.removeAt(index) }) { Icon(Icons.Outlined.Close, contentDescription = "Hapus pengikut") }
                    }
                }
            }
            MemberInput(onAdd = { members.add(it) })

            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton(text = "Kirim pengajuan", loading = submitting, onClick = {
                errors = buildMap {
                    if (suratPengantar.isBlank()) put("surat_pengantar", "Wajib diisi")
                    if (nama.isBlank()) put("nama", "Wajib diisi")
                    if (kategori == "militer" && pangkatNrp.isBlank()) put("pangkat_korps_nrp", "Wajib diisi untuk militer")
                    if (kategori != "militer" && nik.length != 16) put("nik", "NIK harus 16 digit")
                    if (jabatan.isBlank()) put("jabatan_pekerjaan", "Wajib diisi")
                    if (tempatLahir.isBlank()) put("tempat_lahir", "Wajib diisi")
                    if (tanggalLahir.isBlank()) put("tanggal_lahir", "Wajib diisi")
                    if (agama.isBlank()) put("agama", "Pilih agama")
                    if (alamat.isBlank()) put("alamat", "Wajib diisi")
                    if (peruntukan.isBlank()) put("peruntukan", "Wajib diisi")
                    if (photo1 == null) put("foto_1", "Foto wajib dilampirkan")
                    if (pernikahan && photo2 == null) put("foto_2", "Foto calon istri wajib dilampirkan")
                }
                val p1 = photo1
                if (errors.isEmpty() && p1 != null) {
                    val fields = buildMap {
                        put("kategori_personel", kategori)
                        put("is_pernikahan", if (pernikahan) "1" else "0")
                        put("surat_pengantar", suratPengantar.trim())
                        put("nama", nama.trim())
                        if (kategori == "militer") put("pangkat_korps_nrp", pangkatNrp.trim()) else put("nik", nik)
                        put("jabatan_pekerjaan", jabatan.trim())
                        put("tempat_lahir", tempatLahir.trim())
                        put("tanggal_lahir", tanggalLahir)
                        put("jenis_kelamin", jenisKelamin)
                        put("agama", agama)
                        put("alamat", alamat.trim())
                        put("peruntukan", peruntukan.trim())
                    }
                    vm.submit(fields, members.toList(), p1, if (pernikahan) photo2 else null, onDone = onSubmitted)
                }
            })
            Spacer(Modifier.height(Spacing.xxl))
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Text(
        text,
        style = MaterialTheme.typography.titleSmall,
        color = MaterialTheme.colorScheme.primary,
        modifier = Modifier.padding(top = Spacing.lg, bottom = Spacing.xs),
    )
}

@Composable
private fun PhotoSlot(label: String, photo: PickedPhoto?, error: String?, onPick: () -> Unit, onClear: () -> Unit) {
    SindenCard(Modifier.fillMaxWidth(), onClick = onPick, contentPadding = Spacing.md) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (photo != null) {
                Image(
                    bitmap = photo.preview.asImageBitmap(),
                    contentDescription = label,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.size(64.dp).clip(MaterialTheme.shapes.small),
                )
            } else {
                Icon(Icons.Outlined.AddAPhoto, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(40.dp))
            }
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(label, style = MaterialTheme.typography.titleSmall)
                Text(
                    error ?: if (photo != null) "Ketuk untuk mengganti" else "Ketuk untuk memilih dari galeri (JPG/PNG)",
                    style = MaterialTheme.typography.bodySmall,
                    color = if (error != null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (photo != null) IconButton(onClick = onClear) { Icon(Icons.Outlined.Close, contentDescription = "Hapus foto") }
        }
    }
}

@Composable
private fun MemberInput(onAdd: (SkhppMemberInput) -> Unit) {
    var nama by rememberSaveable { mutableStateOf("") }
    var nrp by rememberSaveable { mutableStateOf("") }
    var jabatan by rememberSaveable { mutableStateOf("") }
    SindenTextField(nama, { nama = it }, "Nama pengikut")
    SindenTextField(nrp, { nrp = it }, "Pangkat / NRP / NIK pengikut")
    SindenTextField(jabatan, { jabatan = it }, "Jabatan pengikut")
    OutlinedButton(
        onClick = {
            onAdd(SkhppMemberInput(nama.trim(), nrp.trim(), jabatan.trim()))
            nama = ""; nrp = ""; jabatan = ""
        },
        enabled = nama.isNotBlank() && nrp.isNotBlank() && jabatan.isNotBlank(),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Icon(Icons.Outlined.PersonAdd, contentDescription = null)
        Spacer(Modifier.width(Spacing.sm))
        Text("Tambah pengikut")
    }
}
