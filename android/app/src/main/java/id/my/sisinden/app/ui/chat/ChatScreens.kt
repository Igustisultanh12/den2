package id.my.sisinden.app.ui.chat

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.outlined.AttachFile
import androidx.compose.material.icons.outlined.Call
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Forum
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.LockOpen
import androidx.compose.material.icons.outlined.MoreVert
import androidx.compose.material.icons.outlined.PersonAdd
import androidx.compose.material.icons.outlined.Videocam
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.FilledIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleResumeEffect
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.notification.CommsState
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.CountBadge
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.ErrorState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.ListSkeleton
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.ChatAttachmentDto
import id.my.sisinden.app.data.model.ChatMessageDto
import id.my.sisinden.app.data.model.ChatThreadDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import kotlinx.coroutines.delay

/** Jalankan [block] berkala hanya saat layar tampil (RESUMED). */
@Composable
private fun PollWhileVisible(key: Any, intervalMs: Long, block: suspend () -> Unit) {
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(key, lifecycle) {
        lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                delay(intervalMs)
                block()
            }
        }
    }
}

/**
 * Pintu masuk Chat Dinas: operator melihat daftar utas, personel langsung
 * ke utas Pusat Komunikasi Dinas miliknya.
 */
@Composable
fun ChatHomeScreen(
    repository: SindenRepository,
    myUserId: Int,
    onBack: () -> Unit,
    onOpenThread: (uuid: String, title: String) -> Unit,
    onCall: (uuid: String, video: Boolean, peer: String) -> Unit,
) {
    val vm: ChatHomeViewModel = viewModel { ChatHomeViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()

    // Notifikasi chat di bilah status ditahan selama layar ini tampil.
    LifecycleResumeEffect(Unit) {
        CommsState.chatScreenVisible = true
        onPauseOrDispose { CommsState.chatScreenVisible = false }
    }

    when (val s = state) {
        is LoadState.Ready -> {
            val data = s.data
            val thread = data.thread
            if (data.isOperator == true) {
                OperatorThreadList(vm, onBack, onOpenThread)
            } else if (thread != null) {
                ChatThreadScreen(
                    repository = repository,
                    uuid = thread.uuid,
                    title = "Pusat Komunikasi Dinas",
                    isOperator = false,
                    myUserId = myUserId,
                    onBack = onBack,
                    onCall = { video -> onCall(thread.uuid, video, "Operator Dinas SINDEN") },
                )
            } else {
                SindenScaffold(title = "Chat Dinas", onBack = onBack) { padding ->
                    EmptyState("Chat belum tersedia", "Utas percakapan tidak dapat dibuat.", Modifier.padding(padding), icon = Icons.Outlined.Forum)
                }
            }
        }
        else -> SindenScaffold(title = "Chat Dinas", subtitle = "Pesan & panggilan video", onBack = onBack) { padding ->
            LoadStateContent(s, onRetry = { vm.load() }, modifier = Modifier.padding(padding)) {}
        }
    }
}

@Composable
private fun OperatorThreadList(
    vm: ChatHomeViewModel,
    onBack: () -> Unit,
    onOpenThread: (String, String) -> Unit,
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val filter by vm.filter.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    var composing by rememberSaveable { mutableStateOf(false) }

    PollWhileVisible("threads", 5_000) { vm.refreshThreads() }
    // Muat ulang segera saat kembali dari utas.
    LaunchedEffect(Unit) { vm.refreshThreads() }

    val data = (state as? LoadState.Ready)?.data
    val stats = data?.stats
    SindenScaffold(
        title = "Chat Dinas",
        subtitle = stats?.let { "${it.totalOpen} aktif · ${it.totalUnread} belum dibaca" } ?: "Pusat komunikasi personel",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = { composing = true },
                icon = { Icon(Icons.Outlined.PersonAdd, contentDescription = null) },
                text = { Text("Chat baru") },
            )
        },
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize()) {
            SearchField(search, vm::setSearch, "Cari nama, NRP, atau perihal", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            FilterChipRow(ThreadFilter.entries, filter, { it.label }, vm::setFilter)
            Spacer(Modifier.height(Spacing.sm))
            val threads = data?.threads.orEmpty()
            if (threads.isEmpty()) {
                EmptyState("Belum ada percakapan", "Mulai percakapan dinas dengan menekan \"Chat baru\".", icon = Icons.Outlined.Forum)
            } else {
                LazyColumn(
                    contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                    verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                ) {
                    items(threads, key = { it.uuid }) { t -> ThreadRow(t) { onOpenThread(t.uuid, t.user?.fullName ?: t.subject.orEmpty()) } }
                }
            }
        }
    }

    if (composing) {
        val results by vm.userResults.collectAsStateWithLifecycle()
        val busy by vm.busy.collectAsStateWithLifecycle()
        var query by rememberSaveable { mutableStateOf("") }
        FormSheet(title = "Mulai percakapan dinas", onDismiss = { composing = false }) {
            SearchField(query, { query = it; vm.searchUsers(it) }, "Cari personel (min. 2 huruf)")
            Spacer(Modifier.height(Spacing.md))
            if (busy) CircularProgressIndicator(Modifier.align(Alignment.CenterHorizontally))
            results.forEach { u ->
                ListRowCard(
                    title = u.fullName,
                    subtitle = listOfNotNull(u.nrp?.let { "NRP $it" }, u.role).joinToString(" · "),
                    leading = { InitialsAvatar(u.displayName.initials(), size = 40.dp) },
                    trailing = { if (u.existingThreadUuid != null) StatusChip("Aktif", Tone.SUCCESS) },
                    onClick = {
                        vm.startChat(u) { uuid ->
                            composing = false
                            onOpenThread(uuid, u.fullName)
                        }
                    },
                    modifier = Modifier.padding(bottom = Spacing.sm),
                )
            }
            if (query.trim().length >= 2 && results.isEmpty()) {
                Text("Tidak ada personel yang cocok.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun ThreadRow(t: ChatThreadDto, onClick: () -> Unit) {
    val latest = t.latestMessage
    val preview = when {
        latest == null -> "Belum ada pesan"
        !latest.message.isNullOrBlank() -> "${if (latest.senderType == "ADMIN") "Anda: " else ""}${latest.message}"
        latest.hasAttachments -> "Mengirim lampiran"
        else -> "Pesan"
    }
    ListRowCard(
        title = t.user?.fullName ?: t.subject.orEmpty(),
        subtitle = preview,
        supporting = listOfNotNull(t.lastMessageAt, if (t.isOnline) "Online" else null).joinToString(" · "),
        leading = {
            Box {
                InitialsAvatar(t.user?.displayName.orEmpty().initials(), size = 44.dp)
                if (t.isOnline) {
                    Box(
                        Modifier.align(Alignment.BottomEnd).size(12.dp).clip(CircleShape)
                            .background(MaterialTheme.colorScheme.surface).padding(2.dp).clip(CircleShape).background(Color(0xFF16A34A)),
                    )
                }
            }
        },
        trailing = {
            if (!t.isOpen) StatusChip("Ditutup", Tone.NEUTRAL)
            CountBadge(t.unreadAdmin)
        },
        onClick = onClick,
    )
}

private fun String.initials(): String =
    split(" ").filter { it.isNotBlank() && it.first().isLetter() }.take(2).joinToString("") { it.first().uppercase() }.ifEmpty { "P" }

/** Utas percakapan (gelembung pesan, lampiran, panggilan). */
@Composable
fun ChatThreadScreen(
    repository: SindenRepository,
    uuid: String,
    title: String,
    isOperator: Boolean,
    myUserId: Int,
    onBack: () -> Unit,
    onCall: (video: Boolean) -> Unit,
) {
    val context = LocalContext.current
    val vm: ChatThreadViewModel = viewModel(key = "chat-$uuid") { ChatThreadViewModel(repository, context.applicationContext, uuid) }
    val ui by vm.ui.collectAsStateWithLifecycle()
    val attachments by vm.attachments.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    var text by rememberSaveable(uuid) { mutableStateOf("") }
    var menu by remember { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf(false) }
    val listState = rememberLazyListState()
    val mySide = if (isOperator) "ADMIN" else "USER"

    LifecycleResumeEffect(uuid) {
        CommsState.activeChatThread = uuid
        onPauseOrDispose {
            if (CommsState.activeChatThread == uuid) CommsState.activeChatThread = null
        }
    }
    PollWhileVisible(uuid, 3_000) { vm.poll() }

    LaunchedEffect(ui.messages.size) {
        if (ui.messages.isNotEmpty()) listState.animateScrollToItem(ui.messages.lastIndex)
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) { uris ->
        if (uris.isNotEmpty()) vm.addAttachments(uris)
    }

    SindenScaffold(
        title = title,
        subtitle = when {
            ui.remoteTyping -> "sedang mengetik…"
            !ui.open -> "Sesi ditutup"
            else -> "Chat Dinas"
        },
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            IconButton(onClick = { onCall(false) }, enabled = ui.open) { Icon(Icons.Outlined.Call, contentDescription = "Panggilan suara") }
            IconButton(onClick = { onCall(true) }, enabled = ui.open) { Icon(Icons.Outlined.Videocam, contentDescription = "Panggilan video") }
            if (isOperator) {
                Box {
                    IconButton(onClick = { menu = true }) { Icon(Icons.Outlined.MoreVert, contentDescription = "Opsi utas") }
                    DropdownMenu(expanded = menu, onDismissRequest = { menu = false }) {
                        DropdownMenuItem(
                            text = { Text(if (ui.open) "Tutup sesi" else "Buka kembali sesi") },
                            leadingIcon = { Icon(if (ui.open) Icons.Outlined.Lock else Icons.Outlined.LockOpen, contentDescription = null) },
                            onClick = { menu = false; vm.toggleStatus() },
                        )
                        DropdownMenuItem(
                            text = { Text("Hapus utas") },
                            leadingIcon = { Icon(Icons.Outlined.DeleteOutline, contentDescription = null) },
                            onClick = { menu = false; confirmDelete = true },
                        )
                    }
                }
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize().imePadding()) {
            Box(Modifier.weight(1f).fillMaxWidth()) {
                when {
                    ui.loading -> ListSkeleton(Modifier.padding(top = Spacing.md))
                    ui.error != null && ui.messages.isEmpty() -> ErrorState(ui.error!!, onRetry = null, modifier = Modifier.align(Alignment.Center))
                    ui.messages.isEmpty() -> EmptyState(
                        "Belum ada pesan",
                        if (isOperator) "Kirim pesan pertama kepada personel." else "Sampaikan pertanyaan atau laporan Anda kepada operator dinas.",
                        icon = Icons.Outlined.Forum,
                    )
                    else -> LazyColumn(
                        state = listState,
                        contentPadding = PaddingValues(horizontal = Spacing.lg, vertical = Spacing.md),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        items(ui.messages, key = { it.id }) { m ->
                            MessageBubble(m, mine = m.senderType == mySide, context = context)
                        }
                    }
                }
            }
            if (attachments.isNotEmpty()) {
                LazyRow(
                    contentPadding = PaddingValues(horizontal = Spacing.lg),
                    horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                ) {
                    items(attachments) { a ->
                        Surface(shape = RoundedCornerShape(50), color = MaterialTheme.colorScheme.secondaryContainer) {
                            Row(Modifier.padding(start = Spacing.md), verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    "${a.upload.fileName} · ${a.sizeLabel}",
                                    style = MaterialTheme.typography.labelMedium,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                    modifier = Modifier.widthIn(max = 200.dp),
                                )
                                IconButton(onClick = { vm.removeAttachment(a) }) { Icon(Icons.Outlined.Close, contentDescription = "Hapus lampiran") }
                            }
                        }
                    }
                }
            }
            if (!ui.open) {
                Text(
                    "Sesi percakapan ini telah ditutup operator.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth().padding(Spacing.md),
                )
            } else {
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = Spacing.sm, vertical = Spacing.sm),
                    verticalAlignment = Alignment.Bottom,
                ) {
                    IconButton(onClick = { context.expectExternalActivity(); picker.launch(ChatThreadViewModel.PICKER_MIME) }) {
                        Icon(Icons.Outlined.AttachFile, contentDescription = "Lampirkan berkas")
                    }
                    TextField(
                        value = text,
                        onValueChange = {
                            text = it.take(5000)
                            if (it.isNotBlank()) vm.onTyping()
                        },
                        modifier = Modifier.weight(1f).heightIn(min = 48.dp),
                        placeholder = { Text("Tulis pesan dinas…") },
                        maxLines = 5,
                        shape = RoundedCornerShape(24.dp),
                        colors = TextFieldDefaults.colors(
                            focusedIndicatorColor = Color.Transparent,
                            unfocusedIndicatorColor = Color.Transparent,
                            disabledIndicatorColor = Color.Transparent,
                            focusedContainerColor = MaterialTheme.colorScheme.surface,
                            unfocusedContainerColor = MaterialTheme.colorScheme.surface,
                        ),
                    )
                    Spacer(Modifier.width(Spacing.xs))
                    FilledIconButton(
                        onClick = { vm.send(text) { text = "" } },
                        enabled = !ui.sending && (text.isNotBlank() || attachments.isNotEmpty()),
                        modifier = Modifier.size(48.dp),
                    ) {
                        if (ui.sending) CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                        else Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Kirim")
                    }
                }
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = "Hapus utas?",
            message = "Seluruh pesan dan lampiran pada percakapan ini akan dihapus permanen.",
            confirmLabel = "Hapus",
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = { confirmDelete = false; vm.delete(onBack) },
        )
    }
}

@Composable
private fun MessageBubble(m: ChatMessageDto, mine: Boolean, context: Context) {
    val time = Fmt.parseDateTime(m.createdAt)?.let { Fmt.time(it) }.orEmpty()
    if (m.isSystem) {
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            Text(
                listOf(m.message.orEmpty(), time).filter { it.isNotBlank() }.joinToString(" · "),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.7f))
                    .padding(horizontal = Spacing.md, vertical = Spacing.xs),
            )
        }
        return
    }
    val bg = if (mine) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surface
    val fg = if (mine) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurface
    val shape = RoundedCornerShape(
        topStart = 18.dp, topEnd = 18.dp,
        bottomStart = if (mine) 18.dp else 4.dp,
        bottomEnd = if (mine) 4.dp else 18.dp,
    )
    Box(Modifier.fillMaxWidth(), contentAlignment = if (mine) Alignment.CenterEnd else Alignment.CenterStart) {
        Column(
            Modifier.widthIn(max = 300.dp).clip(shape).background(bg).padding(horizontal = Spacing.md, vertical = Spacing.sm),
        ) {
            if (!mine) {
                Text(m.senderName.orEmpty(), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(2.dp))
            }
            m.attachments.orEmpty().forEach { a ->
                AttachmentView(a, fg, context)
                Spacer(Modifier.height(Spacing.xs))
            }
            if (!m.message.isNullOrBlank()) {
                Text(m.message, style = MaterialTheme.typography.bodyMedium, color = fg)
            }
            Text(
                time,
                style = MaterialTheme.typography.labelSmall,
                color = fg.copy(alpha = 0.7f),
                modifier = Modifier.align(Alignment.End),
            )
        }
    }
}

@Composable
private fun AttachmentView(a: ChatAttachmentDto, fg: Color, context: Context) {
    val url = a.publicUrl(SessionStore.DEFAULT_BASE_URL)
    val open = { if (url != null) openUrl(context, url) }
    if (a.isImage && url != null) {
        AsyncImage(
            model = url,
            contentDescription = a.originalName ?: "Gambar lampiran",
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxWidth().height(180.dp).clip(RoundedCornerShape(12.dp)).clickable { open() },
        )
    } else {
        Row(
            Modifier.clip(RoundedCornerShape(12.dp)).background(fg.copy(alpha = 0.1f)).clickable { open() }.padding(Spacing.sm),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Outlined.Description, contentDescription = null, tint = fg)
            Spacer(Modifier.width(Spacing.sm))
            Column {
                Text(a.originalName ?: "Lampiran", color = fg, style = MaterialTheme.typography.labelLarge, maxLines = 1, overflow = TextOverflow.Ellipsis)
                if (a.size > 0) Text(ChatThreadViewModel.sizeLabel(a.size), color = fg.copy(alpha = 0.7f), style = MaterialTheme.typography.labelSmall)
            }
        }
    }
}

private fun openUrl(context: Context, url: String) {
    try {
        context.expectExternalActivity()
        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    } catch (_: ActivityNotFoundException) {
        // Tidak ada aplikasi peramban/penampil: abaikan.
    }
}
