package id.my.sisinden.app.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material3.LocalContentColor
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.Blue600
import id.my.sisinden.app.core.ui.theme.SindenTheme
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import id.my.sisinden.app.core.ui.theme.Spacing

/**
 * Kerangka halaman modul. Top bar mengikuti kit Livin: latar biru, judul di
 * tengah, tombol kembali berlabel "Kembali" (ikon + teks, ramah lansia).
 * Di mode gelap top bar berupa kaca. Latar halaman transparan agar cahaya
 * [GlassBackground] dari navigasi utama terlihat.
 */
@Composable
fun SindenScaffold(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    onBack: (() -> Unit)? = null,
    snackbarHostState: SnackbarHostState? = null,
    actions: @Composable RowScope.() -> Unit = {},
    floatingActionButton: @Composable () -> Unit = {},
    content: @Composable (PaddingValues) -> Unit,
) {
    Scaffold(
        modifier = modifier,
        containerColor = Color.Transparent,
        contentWindowInsets = WindowInsets(0, 0, 0, 0),
        topBar = { SindenTopBar(title = title, subtitle = subtitle, onBack = onBack, actions = actions) },
        snackbarHost = { if (snackbarHostState != null) SnackbarHost(snackbarHostState) },
        floatingActionButton = floatingActionButton,
        content = content,
    )
}

/** Top bar biru Livin / kaca (gelap). */
@Composable
fun SindenTopBar(
    title: String,
    subtitle: String? = null,
    onBack: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
) {
    val dark = SindenTheme.isDark
    val glass = SindenTheme.glass
    val bg = if (dark) {
        Modifier.background(glass.fillStrong)
    } else {
        Modifier.background(Brush.horizontalGradient(listOf(Blue600, TopBarBlueEnd)))
    }
    CompositionLocalProvider(LocalContentColor provides Color.White) {
        Row(
            Modifier
                .fillMaxWidth()
                .then(bg)
                .statusBarsPadding()
                .heightIn(min = 68.dp)
                .padding(horizontal = Spacing.xs),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.widthIn(min = 64.dp), contentAlignment = Alignment.CenterStart) {
                if (onBack != null) LabeledBackButton(onBack)
            }
            Column(
                Modifier.weight(1f).padding(vertical = Spacing.sm),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(
                    title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Black,
                    color = Color.White,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    textAlign = TextAlign.Center,
                )
                if (!subtitle.isNullOrBlank()) {
                    Text(
                        subtitle,
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.88f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        textAlign = TextAlign.Center,
                    )
                }
            }
            Row(
                Modifier.widthIn(min = 64.dp),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically,
                content = actions,
            )
        }
    }
}

/** Ikon kembali + label "Kembali" di bawahnya (pola Livin). */
@Composable
fun LabeledBackButton(onClick: () -> Unit) {
    Column(
        Modifier
            .clip(MaterialTheme.shapes.small)
            .clickable(role = Role.Button, onClickLabel = "Kembali", onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null, modifier = Modifier.size(26.dp))
        Text("Kembali", style = MaterialTheme.typography.labelSmall, color = Color.White)
    }
}

/** Ujung kanan gradien top bar: lebih terang dari primer namun teks putih tetap ≥ 4:1. */
private val TopBarBlueEnd = Color(0xFF1D84F0)

/** Wadah pull-to-refresh standar. */
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun RefreshableBox(
    isRefreshing: Boolean,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit,
) {
    PullToRefreshBox(
        isRefreshing = isRefreshing,
        onRefresh = onRefresh,
        modifier = modifier.fillMaxSize(),
        content = content,
    )
}

/** Judul seksi gaya Livin ("Fitur Utama Anda" + tautan biru "Atur ›"). */
@Composable
fun SectionHeader(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    Row(
        modifier.fillMaxWidth().padding(horizontal = Spacing.screen),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onBackground)
            if (!subtitle.isNullOrBlank()) {
                Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        if (actionLabel != null && onAction != null) {
            TextButton(onClick = onAction) {
                Text(actionLabel, style = MaterialTheme.typography.labelLarge)
                Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null, modifier = Modifier.size(20.dp))
            }
        }
    }
}

/** Isi layar dengan state Loading/Failed/Ready yang konsisten. */
@Composable
fun <T> LoadStateContent(
    state: LoadState<T>,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    loading: @Composable () -> Unit = { ListSkeleton(Modifier.padding(top = Spacing.md)) },
    content: @Composable (T) -> Unit,
) {
    Box(modifier.fillMaxSize()) {
        when (state) {
            is LoadState.Loading -> loading()
            is LoadState.Failed -> ErrorState(state.error, onRetry, Modifier.align(Alignment.Center))
            is LoadState.Ready -> content(state.data)
        }
    }
}
