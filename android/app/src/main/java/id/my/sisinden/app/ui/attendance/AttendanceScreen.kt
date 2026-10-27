package id.my.sisinden.app.ui.attendance

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.DirectionsCar
import androidx.compose.material.icons.outlined.EventBusy
import androidx.compose.material.icons.outlined.Face
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.PhotoCamera
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Security
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.AttendanceDto
import id.my.sisinden.app.data.model.AttendanceStatus
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import java.time.LocalDate

private fun AttendanceStatus.icon(): ImageVector = when (this) {
    AttendanceStatus.HADIR -> Icons.Outlined.Business
    AttendanceStatus.DINAS_LUAR -> Icons.Outlined.DirectionsCar
    AttendanceStatus.PIKET -> Icons.Outlined.Security
    AttendanceStatus.IZIN -> Icons.Outlined.EventBusy
}

private fun statusTone(code: String?): Tone = when (code) {
    "hadir" -> Tone.SUCCESS
    "dinas_luar" -> Tone.INFO
    "piket" -> Tone.WARNING
    else -> Tone.NEUTRAL
}

@Composable
fun AttendanceScreen(repository: SindenRepository, canSubmit: Boolean) {
    val context = LocalContext.current
    val vm: AttendanceViewModel = viewModel { AttendanceViewModel(repository, context.applicationContext) }
    val state by vm.state.collectAsStateWithLifecycle()
    val form by vm.form.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    var showCamera by rememberSaveable { mutableStateOf(false) }
    if (showCamera) {
        SmileCameraDialog(
            onCaptured = { photo ->
                vm.setPhoto(photo)
                showCamera = false
            },
            onDismiss = { showCamera = false },
        )
    }

    SindenScaffold(title = "Presensi", subtitle = Fmt.fullDate(LocalDate.now()), snackbarHostState = snackbar) { padding ->
        RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }, Modifier.padding(padding)) {
            LoadStateContent(state = state, onRetry = { vm.load() }) { data ->
                LazyColumn(
                    Modifier.fillMaxSize().imePadding(),
                    contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = Spacing.xxl),
                    verticalArrangement = Arrangement.spacedBy(Spacing.md),
                ) {
                    item { TodayCard(data.todayAttendance, data.totalAttendedToday, data.totalActivePersonnel) }

                    if (canSubmit) {
                        item {
                            SindenCard(Modifier.fillMaxWidth()) {
                                Text(
                                    if (data.todayAttendance == null) "Lapor kehadiran" else "Perbarui laporan hari ini",
                                    style = MaterialTheme.typography.titleMedium,
                                )
                                Spacer(Modifier.height(Spacing.md))
                                Column(Modifier.selectableGroup(), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                                    AttendanceStatus.entries.chunked(2).forEach { row ->
                                        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                                            row.forEach { st ->
                                                StatusOption(
                                                    status = st,
                                                    selected = form.status == st,
                                                    onSelect = { vm.setStatus(st) },
                                                    modifier = Modifier.weight(1f),
                                                )
                                            }
                                        }
                                    }
                                }
                                Spacer(Modifier.height(Spacing.md))
                                SindenTextField(
                                    value = form.notes,
                                    onValueChange = vm::setNotes,
                                    label = "Catatan (opsional)",
                                    placeholder = "Mis. tujuan dinas luar",
                                    singleLine = false,
                                    minLines = 2,
                                )
                                Spacer(Modifier.height(Spacing.sm))
                                LocationRow(form = form, onRetry = vm::fetchLocation)
                                Spacer(Modifier.height(Spacing.md))
                                SmilePhotoRow(
                                    photo = form.photo,
                                    onCapture = { showCamera = true },
                                    onRetake = {
                                        vm.setPhoto(null)
                                        showCamera = true
                                    },
                                )
                                Spacer(Modifier.height(Spacing.lg))
                                PrimaryButton(
                                    text = if (data.todayAttendance == null) "Kirim presensi" else "Perbarui presensi",
                                    onClick = vm::submit,
                                    loading = form.submitting,
                                    enabled = form.canSubmit,
                                )
                                if (!form.canSubmit && !form.submitting) {
                                    Text(
                                        "Lokasi GPS dan foto senyum wajib sebelum presensi dikirim.",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        modifier = Modifier.padding(top = Spacing.sm),
                                    )
                                }
                            }
                        }
                    }

                    item {
                        Text("Riwayat 30 hari", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = Spacing.sm))
                    }
                    val history = data.attendances.orEmpty()
                    if (history.isEmpty()) {
                        item {
                            Text(
                                "Belum ada riwayat presensi.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    } else {
                        items(history, key = { it.id }) { HistoryRow(it) }
                    }
                }
            }
        }
    }
}

@Composable
private fun TodayCard(today: AttendanceDto?, attended: Int, active: Int) {
    val tone = if (today != null) Tone.SUCCESS else Tone.WARNING
    SindenCard(
        Modifier.fillMaxWidth(),
        border = BorderStroke(1.dp, toneAccent(tone).copy(alpha = 0.4f)),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconBadge(if (today != null) Icons.Outlined.CheckCircle else Icons.Outlined.EventBusy, toneAccent(tone), size = 52.dp, iconSize = 26.dp)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(
                    if (today != null) "Sudah presensi" else "Belum presensi",
                    style = MaterialTheme.typography.titleMedium,
                )
                Text(
                    if (today != null) "${today.statusLabel} · ${today.timeShort ?: "-"} WIB" else "Silakan lapor kehadiran hari ini.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                today?.locationName?.takeIf { it.isNotBlank() }?.let {
                    Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        Spacer(Modifier.height(Spacing.md))
        Text(
            "Kehadiran satuan hari ini: $attended dari $active personel aktif",
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun StatusOption(status: AttendanceStatus, selected: Boolean, onSelect: () -> Unit, modifier: Modifier = Modifier) {
    val color = if (selected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface
    val border = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant
    Surface(
        modifier = modifier
            .height(64.dp)
            .selectable(selected = selected, onClick = onSelect, role = Role.RadioButton),
        shape = MaterialTheme.shapes.small,
        color = color,
        border = BorderStroke(if (selected) 1.5.dp else 1.dp, border),
    ) {
        Row(Modifier.padding(horizontal = Spacing.md), verticalAlignment = Alignment.CenterVertically) {
            Icon(
                status.icon(),
                contentDescription = null,
                tint = if (selected) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.width(Spacing.sm))
            Text(
                status.label,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                color = if (selected) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface,
            )
        }
    }
}

@Composable
private fun LocationRow(form: AttendanceForm, onRetry: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Outlined.LocationOn, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        Spacer(Modifier.width(Spacing.sm))
        Column(Modifier.weight(1f)) {
            val loc = form.location
            when {
                form.locating -> Text("Mengambil lokasi GPS…", style = MaterialTheme.typography.bodyMedium)
                loc != null -> {
                    Text("Lokasi GPS terlampir", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                    Text(
                        String.format(java.util.Locale.US, "%.5f, %.5f", loc.latitude, loc.longitude) + (loc.accuracyMeters?.let { " (±${it.toInt()} m)" } ?: ""),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                else -> {
                    Text("Lokasi GPS wajib", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                    Text(
                        form.locationError ?: "Menunggu lokasi dari GPS perangkat.",
                        style = MaterialTheme.typography.bodySmall,
                        color = if (form.locationError != null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
        when {
            form.locating -> CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
            form.location != null -> IconButton(onClick = onRetry) { Icon(Icons.Outlined.Refresh, contentDescription = "Perbarui lokasi") }
            else -> OutlinedButton(onClick = onRetry) { Text("Coba lagi") }
        }
    }
}

@Composable
private fun SmilePhotoRow(photo: SmilePhoto?, onCapture: () -> Unit, onRetake: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        if (photo != null) {
            Image(
                bitmap = photo.preview.asImageBitmap(),
                contentDescription = "Foto verifikasi senyum",
                contentScale = ContentScale.Crop,
                modifier = Modifier.size(56.dp).clip(CircleShape),
            )
        } else {
            Icon(Icons.Outlined.Face, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        }
        Spacer(Modifier.width(Spacing.sm))
        Column(Modifier.weight(1f)) {
            if (photo != null) {
                Text("Senyum OK · ${photo.smileScore}%", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                Text("Foto verifikasi siap dikirim", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            } else {
                Text("Foto senyum wajib", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                Text(
                    "Kamera depan otomatis memotret saat senyum ≥ $SMILE_THRESHOLD%",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        if (photo != null) {
            OutlinedButton(onClick = onRetake) { Text("Ulangi") }
        } else {
            OutlinedButton(onClick = onCapture) {
                Icon(Icons.Outlined.PhotoCamera, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(Spacing.xs))
                Text("Ambil")
            }
        }
    }
}

@Composable
private fun HistoryRow(item: AttendanceDto) {
    ListRowCard(
        title = Fmt.parseDate(item.dateOnly)?.let { Fmt.fullDate(it) } ?: (item.dateOnly ?: "-"),
        subtitle = listOfNotNull(
            item.timeShort?.let { "$it WIB" },
            item.smileScore?.let { "Senyum $it%" },
            item.locationName?.takeIf { it.isNotBlank() },
        ).joinToString(" · "),
        supporting = item.notes,
        trailing = { StatusChip(item.statusLabel, statusTone(item.status)) },
    )
}
