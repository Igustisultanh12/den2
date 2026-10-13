package id.my.sisinden.app.ui.duty

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.repository.SindenRepository

/** Detail satu tanggal jaga: seluruh personel yang bertugas, dikelompokkan per peran. */
@Composable
fun DutyDayScreen(
    repository: SindenRepository,
    spId: Int?,
    date: String,
    canPdf: Boolean,
    onBack: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: DutyDayViewModel = viewModel(key = "duty-$spId-$date") { DutyDayViewModel(repository, spId, date) }
    val state by vm.state.collectAsStateWithLifecycle()
    val parsed = Fmt.parseDate(date)

    SindenScaffold(
        title = parsed?.let { Fmt.fullDate(it) } ?: "Detail Jaga",
        subtitle = parsed?.let { Fmt.relativeDay(it) },
        onBack = onBack,
    ) { padding ->
        LoadStateContent(state = state, onRetry = vm::load, modifier = Modifier.padding(padding)) { roster ->
            val day = roster.days.orEmpty().firstOrNull { it.date == date }
            val sp = roster.sp
            LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = Spacing.xxl),
                verticalArrangement = Arrangement.spacedBy(Spacing.md),
            ) {
                if (sp != null) {
                    item {
                        SindenCard(Modifier.fillMaxWidth()) {
                            SpInfoRow(sp)
                            if (canPdf) {
                                Spacer(Modifier.height(Spacing.md))
                                OutlinedButton(
                                    onClick = {
                                        onOpenPdf("api/mobile/pdf/sp-jaga/${sp.id}", "SP Jaga ${sp.periodeLabel ?: ""}".trim(), "SP_JAGA_${sp.tahun}_${sp.bulan}.pdf")
                                    },
                                    modifier = Modifier.fillMaxWidth(),
                                ) {
                                    Icon(Icons.Outlined.PictureAsPdf, contentDescription = null)
                                    Spacer(Modifier.width(Spacing.sm))
                                    Text("Lihat dokumen SP Jaga (PDF)")
                                }
                            }
                        }
                    }
                }

                if (day == null || day.safeEntries.isEmpty()) {
                    item {
                        EmptyState(
                            title = "Tidak ada jadwal jaga",
                            message = "Tidak ada personel yang terjadwal pada tanggal ini di SP Jaga yang berlaku.",
                            icon = Icons.Outlined.EventAvailable,
                        )
                    }
                } else {
                    if (day.hasMe) {
                        item {
                            SindenCard(
                                Modifier.fillMaxWidth(),
                                containerColor = MaterialTheme.colorScheme.primaryContainer,
                                border = null,
                            ) {
                                Text(
                                    "Anda bertugas pada tanggal ini",
                                    style = MaterialTheme.typography.titleSmall,
                                    color = MaterialTheme.colorScheme.onPrimaryContainer,
                                )
                                Text(
                                    day.safeEntries.filter { it.isMe }.mapNotNull { it.role }.distinct().joinToString(", "),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onPrimaryContainer,
                                )
                            }
                        }
                    }
                    val groups = day.safeEntries.groupBy { it.role ?: "Personel Jaga" }
                    groups.forEach { (role, entries) ->
                        item(key = "group-$role") {
                            SindenCard(Modifier.fillMaxWidth()) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(role, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                                    StatusChip("${entries.size} orang", Tone.INFO)
                                }
                                Spacer(Modifier.height(Spacing.sm))
                                entries.forEachIndexed { i, e ->
                                    if (i > 0) HorizontalDivider(Modifier.padding(vertical = 4.dp), color = MaterialTheme.colorScheme.outlineVariant)
                                    DutyEntryRow(e)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
