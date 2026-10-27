package id.my.sisinden.app.ui.navigation

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.components.glass
import id.my.sisinden.app.core.ui.theme.Blue400
import id.my.sisinden.app.core.ui.theme.Blue600

/**
 * Tab bar mengambang: kapsul kaca (iOS 26 Liquid Glass) berisi ikon + label
 * (Livin: setiap ikon selalu berlabel). Tab "Presensi" — aksi harian paling
 * sering — tampil sebagai tombol bulat menonjol di tengah seperti tombol QRIS
 * pada kit Livin.
 */
@Composable
internal fun SindenBottomBar(
    tabs: List<TabItem>,
    currentRoute: String?,
    onSelect: (String) -> Unit,
) {
    val raised = tabs.size >= 3 && tabs.any { it.route == Routes.ATTENDANCE }
    Box(
        Modifier
            .fillMaxWidth()
            .navigationBarsPadding()
            .padding(start = 14.dp, end = 14.dp, bottom = 10.dp, top = if (raised) 20.dp else 6.dp),
    ) {
        Box(Modifier.matchParentSize().glass(RoundedCornerShape(30.dp), strong = true, elevation = 12.dp))
        Row(
            Modifier
                .fillMaxWidth()
                .height(70.dp)
                .padding(horizontal = 6.dp)
                .selectableGroup(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceEvenly,
        ) {
            tabs.forEach { tab ->
                val selected = currentRoute == tab.route
                if (raised && tab.route == Routes.ATTENDANCE) {
                    RaisedItem(tab, selected) { if (!selected) onSelect(tab.route) }
                } else {
                    BarItem(tab, selected) { if (!selected) onSelect(tab.route) }
                }
            }
        }
    }
}

@Composable
private fun RowScope.BarItem(tab: TabItem, selected: Boolean, onClick: () -> Unit) {
    val scheme = MaterialTheme.colorScheme
    Column(
        Modifier
            .weight(1f)
            .clip(RoundedCornerShape(20.dp))
            .selectable(selected = selected, role = Role.Tab, onClick = onClick)
            .padding(vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            Modifier
                .clip(RoundedCornerShape(16.dp))
                .background(if (selected) scheme.primaryContainer else Color.Transparent)
                .padding(horizontal = 14.dp, vertical = 4.dp),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                if (selected) tab.selectedIcon else tab.icon,
                contentDescription = null,
                tint = if (selected) scheme.primary else scheme.onSurfaceVariant,
                modifier = Modifier.size(26.dp),
            )
        }
        Text(
            tab.label,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = if (selected) scheme.primary else scheme.onSurfaceVariant,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun RowScope.RaisedItem(tab: TabItem, selected: Boolean, onClick: () -> Unit) {
    Column(
        Modifier
            .weight(1f)
            .wrapContentHeight(unbounded = true)
            .offset(y = (-14).dp)
            .selectable(selected = selected, role = Role.Tab, onClick = onClick),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            Modifier
                .size(62.dp)
                .shadow(10.dp, CircleShape, ambientColor = Blue600, spotColor = Blue600)
                .clip(CircleShape)
                .background(Brush.verticalGradient(listOf(Blue400, Blue600)))
                .border(3.dp, Color.White.copy(alpha = 0.75f), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            Icon(tab.selectedIcon, contentDescription = null, tint = Color.White, modifier = Modifier.size(30.dp))
        }
        Text(
            tab.label,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Black,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.padding(top = 4.dp),
            maxLines = 1,
        )
    }
}
