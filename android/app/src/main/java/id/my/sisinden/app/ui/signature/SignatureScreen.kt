package id.my.sisinden.app.ui.signature

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Create
import androidx.compose.material.icons.outlined.Undo
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.SignatureListResponse
import id.my.sisinden.app.data.model.SignatureRequestDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel

class SignatureViewModel(private val repository: SindenRepository) : LoadableViewModel<SignatureListResponse>() {
    init { load() }

    override suspend fun fetch(): ApiResult<SignatureListResponse> = repository.signatureRequests()

    fun approve(id: Int, onDone: () -> Unit) = action("Dokumen disahkan.", onDone) { repository.approveSignature(id) }
    fun reject(id: Int, note: String, onDone: () -> Unit) = action("Dokumen dikembalikan.", onDone) { repository.rejectSignature(id, note.trim()) }
}

private enum class SigFilter(val label: String) { PENDING("Menunggu"), ALL("Semua") }

private fun sigTone(s: SignatureRequestDto): Tone = when (s.normalizedStatus) {
    "approved", "signed", "selesai" -> Tone.SUCCESS
    "rejected", "ditolak" -> Tone.DANGER
    else -> Tone.WARNING
}

private fun sigLabel(s: SignatureRequestDto): String = when (s.normalizedStatus) {
    "approved", "signed", "selesai" -> "Disahkan"
    "rejected", "ditolak" -> "Dikembalikan"
    else -> "Menunggu"
}

@Composable
fun SignatureScreen(repository: SindenRepository, permissions: PermissionSet, onBack: () -> Unit) {
    val vm: SignatureViewModel = viewModel { SignatureViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val canApprove = permissions.has(Perm.SIGNATURE_APPROVE)

    var filter by rememberSaveable { mutableStateOf(SigFilter.PENDING) }
    var detail by remember { mutableStateOf<SignatureRequestDto?>(null) }
    var approving by remember { mutableStateOf<SignatureRequestDto?>(null) }
    var rejecting by remember { mutableStateOf<SignatureRequestDto?>(null) }

    SindenScaffold(
        title = "Tanda Tangan Elektronik",
        subtitle = if (canApprove) "Antrean pengesahan Komandan" else "Status pengajuan Anda",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Column(Modifier.padding(padding)) {
            FilterChipRow(SigFilter.entries, filter, { it.label }, { filter = it })
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { data ->
                    val list = data.requests.orEmpty().filter { filter == SigFilter.ALL || it.isPending }
                    if (list.isEmpty()) {
                        EmptyState(
                            "Tidak ada dokumen",
                            if (filter == SigFilter.PENDING) "Tidak ada dokumen yang menunggu pengesahan." else "Belum ada pengajuan tanda tangan.",
                            icon = Icons.Outlined.Create,
                        )
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = Spacing.xxl),
                            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                        ) {
                            items(list, key = { it.id }) { s ->
                                ListRowCard(
                                    title = s.subject.orEmpty().ifBlank { "Dokumen" },
                                    subtitle = listOfNotNull(s.letterNumber, s.requesterName ?: s.personName).joinToString(" · "),
                                    supporting = Fmt.relative(s.createdAt),
                                    leading = { IconBadge(Icons.Outlined.Create, toneAccent(sigTone(s)), size = 40.dp, iconSize = 20.dp) },
                                    trailing = { StatusChip(sigLabel(s), sigTone(s)) },
                                    onClick = { detail = s },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    detail?.let { s ->
        FormSheet(title = "Detail pengajuan", onDismiss = { detail = null }) {
            Row { StatusChip(sigLabel(s), sigTone(s)) }
            Spacer(Modifier.height(Spacing.sm))
            InfoRow("Perihal", s.subject)
            InfoRow("Nomor", s.letterNumber)
            InfoRow("Pemohon", s.requesterName ?: s.personName)
            InfoRow("Peruntukan", s.peruntukan)
            InfoRow("Catatan", s.note)
            InfoRow("Kode TTE", s.verificationCode)
            InfoRow("Diajukan", Fmt.relative(s.createdAt))
            if (canApprove && s.isPending) {
                Spacer(Modifier.height(Spacing.lg))
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    Button(onClick = { approving = s }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Check, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Sahkan")
                    }
                    OutlinedButton(onClick = { rejecting = s }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Undo, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Kembalikan")
                    }
                }
            }
        }
    }

    approving?.let { s ->
        ConfirmDialog(
            title = "Sahkan dokumen?",
            message = "Stempel QR TTE akan dibubuhkan pada \"${s.subject.orEmpty()}\" seperti pengesahan di web. Pastikan isi dokumen sudah diperiksa.",
            confirmLabel = "Sahkan",
            loading = busy,
            onDismiss = { approving = null },
            onConfirm = { vm.approve(s.id) { approving = null; detail = null } },
        )
    }

    rejecting?.let { s ->
        var note by rememberSaveable { mutableStateOf("") }
        var error by remember { mutableStateOf<String?>(null) }
        FormSheet(title = "Kembalikan dokumen", onDismiss = { rejecting = null }) {
            SindenTextField(note, { note = it; error = null }, "Alasan pengembalian", error = error, singleLine = false, minLines = 3)
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Kirim", loading = busy, onClick = {
                if (note.isBlank()) error = "Alasan wajib diisi"
                else vm.reject(s.id, note) { rejecting = null; detail = null }
            })
        }
    }
}
