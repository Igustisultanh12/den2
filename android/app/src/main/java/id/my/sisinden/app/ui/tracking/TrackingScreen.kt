package id.my.sisinden.app.ui.tracking

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Map
import androidx.compose.material.icons.outlined.PersonPinCircle
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.TrackedPersonnelDto
import id.my.sisinden.app.data.model.TrackingResponse
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.delay
import java.util.Locale

/** Interval pembaruan otomatis daftar posisi saat layar terlihat. */
private const val AUTO_REFRESH_MS = 15_000L

class TrackingViewModel(private val repository: SindenRepository) : LoadableViewModel<TrackingResponse>() {
    init { load() }

    override suspend fun fetch(): ApiResult<TrackingResponse> = repository.tracking()
}

private enum class TrackingFilter(val label: String) { ALL("Semua"), ONLINE("Online"), OFFLINE("Offline") }

/** Pantau Personel: posisi terakhir seluruh personel (Admin, Super Admin, Komandan). */
@Composable
fun TrackingScreen(repository: SindenRepository, onBack: () -> Unit) {
    val vm: TrackingViewModel = viewModel { TrackingViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val context = LocalContext.current

    var query by rememberSaveable { mutableStateOf("") }
    var filter by rememberSaveable { mutableStateOf(TrackingFilter.ALL) }

    // Segarkan otomatis hanya saat layar aktif (berhenti ketika aplikasi di latar).
    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                delay(AUTO_REFRESH_MS)
                vm.load()
            }
        }
    }

    val openMap: (TrackedPersonnelDto) -> Unit = { p ->
        val label = Uri.encode(p.displayName)
        val geo = Intent(Intent.ACTION_VIEW, Uri.parse("geo:${p.latitude},${p.longitude}?q=${p.latitude},${p.longitude}($label)"))
        try {
            context.startActivity(geo)
        } catch (_: ActivityNotFoundException) {
            context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://www.google.com/maps?q=${p.latitude},${p.longitude}")))
        }
    }

    SindenScaffold(
        title = "Pantau Personel",
        subtitle = "Posisi realtime · diperbarui tiap ${AUTO_REFRESH_MS / 1000} detik",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Column(Modifier.padding(padding)) {
            SearchField(query, { query = it }, "Cari nama, NRP, atau jabatan", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            FilterChipRow(TrackingFilter.entries, filter, { it.label }, { filter = it })
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { data ->
                    val all = data.personnel.orEmpty()
                    val q = query.trim().lowercase(Locale.ROOT)
                    val shown = all
                        .filter {
                            when (filter) {
                                TrackingFilter.ALL -> true
                                TrackingFilter.ONLINE -> it.isOnline
                                TrackingFilter.OFFLINE -> !it.isOnline
                            }
                        }
                        .filter { p ->
                            q.isEmpty() || listOf(p.name, p.nrp, p.pangkat, p.jabatan).any { it?.lowercase(Locale.ROOT)?.contains(q) == true }
                        }
                        .sortedWith(compareByDescending<TrackedPersonnelDto> { it.isOnline }.thenBy { it.ageSeconds ?: Long.MAX_VALUE })

                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = Spacing.xxl),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        item {
                            Text(
                                "${data.totalOnline} online dari ${all.size} personel yang membagikan lokasi",
                                style = MaterialTheme.typography.labelLarge,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        if (shown.isEmpty()) {
                            item {
                                EmptyState(
                                    title = "Belum ada data",
                                    message = "Posisi muncul setelah personel login di aplikasi SISINDEN Android.",
                                    icon = Icons.Outlined.PersonPinCircle,
                                )
                            }
                        } else {
                            items(shown, key = { it.userId }) { p -> PersonnelRow(p, onOpenMap = { openMap(p) }) }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun PersonnelRow(p: TrackedPersonnelDto, onOpenMap: () -> Unit) {
    ListRowCard(
        title = p.displayName.ifBlank { "Personel #${p.userId}" },
        subtitle = listOfNotNull(
            ageLabel(p.ageSeconds),
            p.accuracy?.let { "±${it.toInt()} m" },
            p.battery?.let { "Baterai $it%" + if (p.isCharging == true) " ⚡" else "" },
        ).joinToString(" · "),
        supporting = p.jabatan?.takeIf { it.isNotBlank() } ?: p.nrp,
        leading = { InitialsAvatar(p.name ?: "?", highlight = p.isOnline) },
        trailing = {
            StatusChip(if (p.isOnline) "Online" else "Offline", if (p.isOnline) Tone.SUCCESS else Tone.NEUTRAL)
            IconButton(onClick = onOpenMap) { Icon(Icons.Outlined.Map, contentDescription = "Buka di peta") }
        },
        onClick = onOpenMap,
    )
}

private fun ageLabel(seconds: Long?): String = when {
    seconds == null -> "Belum ada data"
    seconds < 60 -> "$seconds dtk lalu"
    seconds < 3600 -> "${seconds / 60} mnt lalu"
    seconds < 86_400 -> "${seconds / 3600} jam lalu"
    else -> "${seconds / 86_400} hari lalu"
}
