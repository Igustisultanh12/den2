package id.my.sisinden.app.ui.violations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.DateField
import id.my.sisinden.app.core.ui.components.DropdownField
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.CreateViolationRequest
import id.my.sisinden.app.data.model.ViolationStatus
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.UiMessages
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class ViolationFormViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _submitting = MutableStateFlow(false)
    val submitting: StateFlow<Boolean> = _submitting.asStateFlow()
    val messages = UiMessages()

    fun submit(request: CreateViolationRequest, onDone: () -> Unit) {
        if (_submitting.value) return
        _submitting.value = true
        viewModelScope.launch {
            when (val res = repository.createViolation(request)) {
                is ApiResult.Success -> onDone()
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _submitting.value = false
        }
    }
}

@Composable
fun ViolationFormScreen(repository: SindenRepository, onBack: () -> Unit, onSubmitted: () -> Unit) {
    val vm: ViolationFormViewModel = viewModel { ViolationFormViewModel(repository) }
    val submitting by vm.submitting.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    var nama by rememberSaveable { mutableStateOf("") }
    var nrp by rememberSaveable { mutableStateOf("") }
    var pangkat by rememberSaveable { mutableStateOf("") }
    var jabatan by rememberSaveable { mutableStateOf("") }
    var satuan by rememberSaveable { mutableStateOf("Denintel Koarmada 2") }
    var kasus by rememberSaveable { mutableStateOf("") }
    var tmt by rememberSaveable { mutableStateOf("") }
    var perkembangan by rememberSaveable { mutableStateOf("") }
    var status by rememberSaveable { mutableStateOf("PROSES") }
    var errors by remember { mutableStateOf(emptyMap<String, String>()) }

    SindenScaffold(title = "Catat Pelanggaran", subtitle = "Kolom sama dengan formulir web", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).imePadding().padding(horizontal = Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.xs),
        ) {
            Text(
                "Lampiran berkas & dokumen putusan diunggah melalui web SISINDEN.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(Spacing.sm))
            SindenTextField(nama, { nama = it }, "Nama prajurit", error = errors["nama"])
            SindenTextField(nrp, { nrp = it }, "NRP", error = errors["nrp"])
            SindenTextField(pangkat, { pangkat = it }, "Pangkat", error = errors["pangkat"])
            SindenTextField(jabatan, { jabatan = it }, "Jabatan", error = errors["jabatan"])
            SindenTextField(satuan, { satuan = it }, "Satuan", error = errors["satuan"])
            SindenTextField(kasus, { kasus = it }, "Uraian kasus", error = errors["kasus"], singleLine = false, minLines = 3)
            DateField(tmt, { tmt = it }, "TMT / tanggal kejadian", error = errors["tmt"])
            SindenTextField(perkembangan, { perkembangan = it }, "Perkembangan awal", error = errors["perkembangan"], singleLine = false, minLines = 2)
            DropdownField(
                label = "Status kasus",
                options = ViolationStatus.all,
                selected = status,
                optionLabel = ViolationStatus::label,
                onSelect = { status = it },
            )
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Simpan catatan", loading = submitting, onClick = {
                errors = buildMap {
                    if (nama.isBlank()) put("nama", "Wajib diisi")
                    if (nrp.isBlank()) put("nrp", "Wajib diisi")
                    if (pangkat.isBlank()) put("pangkat", "Wajib diisi")
                    if (jabatan.isBlank()) put("jabatan", "Wajib diisi")
                    if (satuan.isBlank()) put("satuan", "Wajib diisi")
                    if (kasus.isBlank()) put("kasus", "Wajib diisi")
                    if (tmt.isBlank()) put("tmt", "Wajib diisi")
                    if (perkembangan.isBlank()) put("perkembangan", "Wajib diisi")
                }
                if (errors.isEmpty()) {
                    vm.submit(
                        CreateViolationRequest(nama.trim(), nrp.trim(), pangkat.trim(), jabatan.trim(), satuan.trim(), kasus.trim(), tmt, perkembangan.trim(), status),
                        onDone = onSubmitted,
                    )
                }
            })
            Spacer(Modifier.height(Spacing.xxl))
        }
    }
}
