package id.my.sisinden.app.ui.menu

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.GradientIconTile
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.UserProfile
import id.my.sisinden.app.ui.navigation.Feature
import id.my.sisinden.app.ui.navigation.FeatureGroup

/**
 * Seluruh modul yang diizinkan untuk akun ini, dikelompokkan dan dapat dicari.
 * Daftar berasal dari permission server; modul tanpa izin tidak ditampilkan.
 */
@Composable
fun MenuScreen(
    permissions: PermissionSet,
    user: UserProfile,
    onNavigate: (String) -> Unit,
) {
    var query by rememberSaveable { mutableStateOf("") }
    val q = query.trim().lowercase()
    val features = Feature.permitted(permissions).filter {
        q.isEmpty() || it.title.lowercase().contains(q) || it.description.lowercase().contains(q)
    }
    val grouped = FeatureGroup.entries.associateWith { g -> features.filter { it.group == g } }.filterValues { it.isNotEmpty() }

    SindenScaffold(title = "Menu", subtitle = "Modul sesuai hak akses ${user.roleLabel}") { padding ->
        LazyColumn(
            Modifier.fillMaxSize().padding(padding),
            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, top = Spacing.lg, bottom = Spacing.xxl),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            item {
                SearchField(query, { query = it }, "Cari menu…")
                Spacer(Modifier.height(Spacing.sm))
            }
            if (grouped.isEmpty()) {
                item {
                    EmptyState(
                        title = if (q.isEmpty()) "Belum ada modul" else "Menu tidak ditemukan",
                        message = if (q.isEmpty()) "Akun Anda belum diberi akses modul tambahan. Hubungi Admin."
                        else "Tidak ada menu yang cocok dengan \"$query\".",
                    )
                }
            }
            grouped.forEach { (group, groupFeatures) ->
                item(key = "header-${group.name}") {
                    Column(Modifier.fillMaxWidth().padding(top = Spacing.md, bottom = Spacing.xs)) {
                        Text(group.title, style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onBackground)
                    }
                }
                items(groupFeatures, key = { it.name }) { f ->
                    ListRowCard(
                        title = f.title,
                        subtitle = f.description,
                        leading = { GradientIconTile(f.icon, f.gradient, size = 48.dp, iconSize = 26.dp, corner = 16.dp) },
                        trailing = {
                            Text("Buka", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                            Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        },
                        onClick = { onNavigate(f.route) },
                    )
                }
            }
        }
    }
}
