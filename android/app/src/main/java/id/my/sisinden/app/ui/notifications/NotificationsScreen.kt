package id.my.sisinden.app.ui.notifications

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.DoneAll
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.dataOrNull
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.NotificationDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.UiMessages
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class NotificationsViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _state = MutableStateFlow<LoadState<List<NotificationDto>>>(LoadState.Loading)
    val state: StateFlow<LoadState<List<NotificationDto>>> = _state.asStateFlow()
    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()
    val messages = UiMessages()

    init { load() }

    fun load(pull: Boolean = false) {
        if (pull) _refreshing.value = true else _state.value = LoadState.Loading
        viewModelScope.launch {
            when (val res = repository.notifications()) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data.notifications.orEmpty())
                is ApiResult.Failure -> {
                    if (pull && _state.value is LoadState.Ready) messages.post(res.error.message)
                    else _state.value = LoadState.Failed(res.error)
                }
            }
            _refreshing.value = false
        }
    }

    fun markRead(item: NotificationDto) {
        if (item.isRead) return
        // Pembaruan optimistis; dikembalikan bila server menolak.
        val before = _state.value.dataOrNull ?: return
        _state.value = LoadState.Ready(before.map { if (it.id == item.id) it.copy(isRead = true) else it })
        viewModelScope.launch {
            val res = repository.markNotificationRead(item.id)
            if (res is ApiResult.Failure) {
                _state.value = LoadState.Ready(before)
                messages.post(res.error.message)
            }
        }
    }

    fun markAll() {
        val before = _state.value.dataOrNull ?: return
        if (before.none { !it.isRead }) return
        _state.value = LoadState.Ready(before.map { it.copy(isRead = true) })
        viewModelScope.launch {
            when (val res = repository.markAllNotificationsRead()) {
                is ApiResult.Success -> messages.post("Semua notifikasi ditandai dibaca.")
                is ApiResult.Failure -> {
                    _state.value = LoadState.Ready(before)
                    messages.post(res.error.message)
                }
            }
        }
    }
}

private fun toneFor(type: String?): Tone = when (type?.lowercase()) {
    "success" -> Tone.SUCCESS
    "warning" -> Tone.WARNING
    "danger", "error" -> Tone.DANGER
    "primary", "info" -> Tone.INFO
    else -> Tone.NEUTRAL
}

@Composable
fun NotificationsScreen(repository: SindenRepository, onBack: () -> Unit) {
    val vm: NotificationsViewModel = viewModel { NotificationsViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val unread = state.dataOrNull?.count { !it.isRead } ?: 0

    SindenScaffold(
        title = "Notifikasi",
        subtitle = if (unread > 0) "$unread belum dibaca" else "Semua sudah dibaca",
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            if (unread > 0) {
                IconButton(onClick = vm::markAll) { Icon(Icons.Outlined.DoneAll, contentDescription = "Tandai semua dibaca") }
            }
        },
    ) { padding ->
        RefreshableBox(refreshing, { vm.load(pull = true) }, Modifier.padding(padding)) {
            LoadStateContent(state, onRetry = { vm.load() }) { list ->
                if (list.isEmpty()) {
                    EmptyState(
                        title = "Belum ada notifikasi",
                        message = "Warta dan instruksi kedinasan untuk Anda akan muncul di sini.",
                        icon = Icons.Outlined.NotificationsNone,
                    )
                } else {
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = Spacing.xxl),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        items(list, key = { it.id }) { n ->
                            SindenCard(
                                modifier = Modifier.fillMaxWidth(),
                                onClick = { vm.markRead(n) },
                                containerColor = if (n.isRead) MaterialTheme.colorScheme.surface else MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.35f),
                                contentPadding = Spacing.md,
                            ) {
                                Row(verticalAlignment = Alignment.Top) {
                                    IconBadge(Icons.Outlined.Notifications, toneAccent(toneFor(n.type)), size = 40.dp, iconSize = 20.dp)
                                    Spacer(Modifier.width(Spacing.md))
                                    Column(Modifier.weight(1f)) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text(
                                                n.title.orEmpty().ifBlank { "Notifikasi" },
                                                style = MaterialTheme.typography.titleSmall,
                                                fontWeight = if (n.isRead) FontWeight.Medium else FontWeight.Bold,
                                                modifier = Modifier.weight(1f),
                                            )
                                            if (!n.isRead) {
                                                Box(Modifier.size(8.dp).clip(CircleShape).background(MaterialTheme.colorScheme.primary))
                                            }
                                        }
                                        Text(n.message.orEmpty(), style = MaterialTheme.typography.bodyMedium)
                                        Text(
                                            Fmt.relative(n.createdAt),
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
