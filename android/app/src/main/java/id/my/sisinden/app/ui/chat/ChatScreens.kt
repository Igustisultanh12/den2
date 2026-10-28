package id.my.sisinden.app.ui.chat

import android.Manifest
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.rememberTransformableState
import androidx.compose.foundation.gestures.transformable
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
import androidx.compose.material.icons.filled.Mic
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
import androidx.compose.material.icons.outlined.SupportAgent
import androidx.compose.material.icons.outlined.Videocam
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.FilledIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleResumeEffect
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import coil.compose.SubcomposeAsyncImage
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.notification.CommsState
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.CountBadge
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.ErrorState
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
import id.my.sisinden.app.data.model.ConversationDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import kotlinx.coroutines.delay

private val Online = Color(0xFF16A34A)

/** Jalankan [block] berkala hanya saat layar tampil (RESUMED). */
@Composable
private fun PollWhileVisible(key: Any, intervalMs: Long, block: suspend () -> Unit) {
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(key, lifecycle) {
        lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                block()
                delay(intervalMs)
            }
        }
    }
}

/** Beranda Chat Dinas: daftar percakapan + "Chat baru" ke personel mana pun. */
@Composable
fun ChatHomeScreen(
    repository: SindenRepository,
    onBack: () -> Unit,
    onOpenThread: (uuid: String, title: String) -> Unit,
) {
    val vm: ChatHomeViewModel = viewModel { ChatHomeViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    var composing by rememberSaveable { mutableStateOf(false) }

    // Notifikasi chat di bilah status ditahan selama layar ini tampil.
    LifecycleResumeEffect(Unit) {
        CommsState.chatScreenVisible = true
        onPauseOrDispose { CommsState.chatScreenVisible = false }
    }
    PollWhileVisible("conversations", 4_000) { vm.refresh() }

    val data = (state as? LoadState.Ready)?.data
    SindenScaffold(
        title = "Chat Dinas",
        subtitle = data?.let { if (it.totalUnread > 0) "${it.totalUnread} pesan belum dibaca" else "Pesan, pesan suara & panggilan" } ?: "Pesan & panggilan video",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = { composing = true; vm.searchContacts("") },
                icon = { Icon(Icons.Outlined.PersonAdd, contentDescription = null) },
                text = { Text("Chat baru") },
            )
        },
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize()) {
            SearchField(search, vm::setSearch, "Cari percakapan", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            LoadStateContent(state, onRetry = { vm.load() }) { home ->
                val list = home.conversations.orEmpty()
                if (list.isEmpty()) {
                    EmptyState("Belum ada percakapan", "Tekan \"Chat baru\" untuk mengirim pesan ke Komandan atau personel lain.", icon = Icons.Outlined.Forum)
                } else {
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        items(list, key = { it.uuid ?: "support" }) { c ->
                            ConversationRow(c, myUserId = null) { vm.open(c, onOpenThread) }
                        }
                    }
                }
            }
        }
    }

    if (composing) {
        val contacts by vm.contacts.collectAsStateWithLifecycle()
        val loading by vm.contactsLoading.collectAsStateWithLifecycle()
        var query by rememberSaveable { mutableStateOf("") }
        FormSheet(title = "Chat baru", onDismiss = { composing = false }) {
            SearchField(query, { query = it; vm.searchContacts(it) }, "Cari nama, NRP, atau pangkat")
            Spacer(Modifier.height(Spacing.md))
            if (loading || busy) CircularProgressIndicator(Modifier.align(Alignment.CenterHorizontally))
            contacts.forEach { u ->
                ListRowCard(
                    title = u.fullName,
                    subtitle = u.jabatan?.takeIf { it.isNotBlank() } ?: u.nrp?.let { "NRP $it" } ?: u.role.orEmpty(),
                    leading = { AvatarWithPresence(u.displayName, u.isOnline, 40) },
                    onClick = {
                        vm.startChat(u) { uuid, title ->
                            composing = false
                            onOpenThread(uuid, title)
                        }
                    },
                    modifier = Modifier.padding(bottom = Spacing.sm),
                )
            }
            if (!loading && contacts.isEmpty()) {
                Text("Personel tidak ditemukan.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun AvatarWithPresence(name: String, online: Boolean, size: Int, support: Boolean = false) {
    Box {
        if (support) {
            Box(
                Modifier.size(size.dp).clip(CircleShape).background(MaterialTheme.colorScheme.primary),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Outlined.SupportAgent, contentDescription = null, tint = MaterialTheme.colorScheme.onPrimary) }
        } else {
            InitialsAvatar(name.initials(), size = size.dp)
        }
        if (online) {
            Box(
                Modifier.align(Alignment.BottomEnd).size(12.dp).clip(CircleShape)
                    .background(MaterialTheme.colorScheme.surface).padding(2.dp).clip(CircleShape).background(Online),
            )
        }
    }
}

@Composable
private fun ConversationRow(c: ConversationDto, myUserId: Int?, onClick: () -> Unit) {
    val latest = c.latestMessage
    val preview = when {
        c.isTyping -> "sedang mengetik…"
        latest == null -> c.subtitle ?: "Belum ada pesan"
        else -> (if (myUserId != null && latest.senderId == myUserId) "Anda: " else "") + latest.preview.orEmpty()
    }
    val time = Fmt.parseDateTime(c.lastMessageAt)?.let { Fmt.time(it) }.orEmpty()
    ListRowCard(
        title = c.displayTitle,
        subtitle = preview,
        supporting = listOfNotNull(time.takeIf { latest != null && it.isNotBlank() }, if (c.isOnline) "Online" else null).joinToString(" · ").ifBlank { null },
        leading = { AvatarWithPresence(c.displayTitle, c.isOnline, 44, support = c.isSupport && c.role == "user") },
        trailing = {
            if (!c.isOpen) StatusChip("Ditutup", Tone.NEUTRAL)
            CountBadge(c.unread)
        },
        onClick = onClick,
    )
}

private fun String.initials(): String =
    split(" ").filter { it.isNotBlank() && it.first().isLetter() }.take(2).joinToString("") { it.first().uppercase() }.ifEmpty { "P" }

/** Percakapan: gelembung pesan, foto langsung tampil, pesan suara, panggilan. */
@Composable
fun ChatThreadScreen(
    repository: SindenRepository,
    uuid: String,
    title: String,
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
    var viewer by remember { mutableStateOf<String?>(null) }
    val listState = rememberLazyListState()

    val recorder = remember { VoiceRecorder(context.applicationContext) }
    var recording by remember { mutableStateOf(false) }
    val recordMs = rememberRecordingClock(recording, recorder)
    DisposableEffect(Unit) { onDispose { recorder.cancel() } }
    StopVoicePlaybackOnDispose()

    val micPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) {
            recording = recorder.start()
            if (!recording) vm.messages.post("Mikrofon sedang dipakai aplikasi lain.")
        } else {
            vm.messages.post("Izinkan mikrofon untuk mengirim pesan suara.")
        }
    }
    val startRecording = {
        if (ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) {
            VoicePlayback.stop()
            recording = recorder.start()
            if (!recording) vm.messages.post("Mikrofon sedang dipakai aplikasi lain.")
        } else {
            micPermission.launch(Manifest.permission.RECORD_AUDIO)
        }
    }
    // Batas 5 menit per pesan suara.
    LaunchedEffect(recordMs) {
        if (recording && recordMs >= 5 * 60_000) {
            recording = false
            recorder.stop()?.let(vm::sendVoice)
        }
    }

    LifecycleResumeEffect(uuid) {
        CommsState.activeChatThread = uuid
        onPauseOrDispose {
            if (CommsState.activeChatThread == uuid) CommsState.activeChatThread = null
        }
    }
    PollWhileVisible(uuid, 2_500) { vm.poll() }

    LaunchedEffect(ui.messages.size) {
        if (ui.messages.isNotEmpty()) listState.animateScrollToItem(ui.messages.lastIndex)
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) { uris ->
        if (uris.isNotEmpty()) vm.addAttachments(uris)
    }

    val partner = ui.thread
    SindenScaffold(
        title = partner?.displayTitle ?: title,
        subtitle = when {
            ui.remoteTyping -> "sedang mengetik…"
            !ui.open -> "Sesi ditutup"
            partner?.isOnline == true -> "online"
            else -> partner?.subtitle ?: "Chat Dinas"
        },
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            IconButton(onClick = { onCall(false) }, enabled = ui.open) { Icon(Icons.Outlined.Call, contentDescription = "Panggilan suara") }
            IconButton(onClick = { onCall(true) }, enabled = ui.open) { Icon(Icons.Outlined.Videocam, contentDescription = "Panggilan video") }
            if (ui.isOperator || ui.canDelete) {
                Box {
                    IconButton(onClick = { menu = true }) { Icon(Icons.Outlined.MoreVert, contentDescription = "Opsi percakapan") }
                    DropdownMenu(expanded = menu, onDismissRequest = { menu = false }) {
                        if (ui.isOperator && ui.thread?.isSupport == true) {
                            DropdownMenuItem(
                                text = { Text(if (ui.open) "Tutup sesi" else "Buka kembali sesi") },
                                leadingIcon = { Icon(if (ui.open) Icons.Outlined.Lock else Icons.Outlined.LockOpen, contentDescription = null) },
                                onClick = { menu = false; vm.toggleStatus() },
                            )
                        }
                        if (ui.canDelete) {
                            DropdownMenuItem(
                                text = { Text("Hapus percakapan") },
                                leadingIcon = { Icon(Icons.Outlined.DeleteOutline, contentDescription = null) },
                                onClick = { menu = false; confirmDelete = true },
                            )
                        }
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
                        if (partner?.isSupport == true && partner.role == "user") "Sampaikan pertanyaan atau laporan Anda kepada operator dinas." else "Sapa lebih dulu, kirim pesan suara, atau lakukan panggilan video.",
                        icon = Icons.Outlined.Forum,
                    )
                    else -> LazyColumn(
                        state = listState,
                        contentPadding = PaddingValues(horizontal = Spacing.lg, vertical = Spacing.md),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        items(ui.messages, key = { it.id }) { m ->
                            MessageBubble(
                                m,
                                mine = !m.isSystem && m.senderId == myUserId,
                                showSender = partner?.isSupport == true,
                                context = context,
                                onOpenImage = { viewer = it },
                            )
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
            when {
                !ui.open -> Text(
                    "Sesi percakapan ini telah ditutup operator.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth().padding(Spacing.md),
                )
                recording -> RecordingBar(
                    elapsedMs = recordMs,
                    onCancel = { recording = false; recorder.cancel() },
                    onSend = {
                        recording = false
                        val clip = recorder.stop()
                        if (clip != null) vm.sendVoice(clip) else vm.messages.post("Pesan suara terlalu singkat.")
                    },
                )
                else -> Row(
                    Modifier.fillMaxWidth().padding(horizontal = Spacing.sm, vertical = Spacing.sm),
                    verticalAlignment = Alignment.Bottom,
                ) {
                    IconButton(onClick = { context.expectExternalActivity(); picker.launch(ChatThreadViewModel.PICKER_MIME) }) {
                        Icon(Icons.Outlined.AttachFile, contentDescription = "Lampirkan foto/berkas")
                    }
                    TextField(
                        value = text,
                        onValueChange = {
                            text = it.take(5000)
                            if (it.isNotBlank()) vm.onTyping()
                        },
                        modifier = Modifier.weight(1f).heightIn(min = 48.dp),
                        placeholder = { Text("Ketik pesan") },
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
                    val hasContent = text.isNotBlank() || attachments.isNotEmpty()
                    FilledIconButton(
                        onClick = { if (hasContent) vm.send(text) { text = "" } else startRecording() },
                        enabled = !ui.sending,
                        modifier = Modifier.size(48.dp),
                    ) {
                        when {
                            ui.sending -> CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                            hasContent -> Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Kirim")
                            else -> Icon(Icons.Filled.Mic, contentDescription = "Rekam pesan suara")
                        }
                    }
                }
            }
        }
    }

    viewer?.let { url -> ImageViewer(url) { viewer = null } }

    if (confirmDelete) {
        ConfirmDialog(
            title = "Hapus percakapan?",
            message = "Seluruh pesan dan lampiran pada percakapan ini akan dihapus permanen untuk kedua pihak.",
            confirmLabel = "Hapus",
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = { confirmDelete = false; vm.delete(onBack) },
        )
    }
}

@Composable
private fun RecordingBar(elapsedMs: Long, onCancel: () -> Unit, onSend: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(horizontal = Spacing.md, vertical = Spacing.sm),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(12.dp).clip(CircleShape).background(Color(0xFFE11D48)))
        Spacer(Modifier.width(Spacing.sm))
        Text(formatVoiceDuration(elapsedMs), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        Spacer(Modifier.width(Spacing.sm))
        Text("Merekam pesan suara…", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
        TextButton(onClick = onCancel) { Text("Batal") }
        FilledIconButton(
            onClick = onSend,
            modifier = Modifier.size(48.dp),
            colors = IconButtonDefaults.filledIconButtonColors(containerColor = Online),
        ) { Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Kirim pesan suara", tint = Color.White) }
    }
}

@Composable
private fun MessageBubble(
    m: ChatMessageDto,
    mine: Boolean,
    showSender: Boolean,
    context: Context,
    onOpenImage: (String) -> Unit,
) {
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
    val accent = if (mine) MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.25f) else MaterialTheme.colorScheme.primary
    val shape = RoundedCornerShape(
        topStart = 18.dp, topEnd = 18.dp,
        bottomStart = if (mine) 18.dp else 4.dp,
        bottomEnd = if (mine) 4.dp else 18.dp,
    )
    Box(Modifier.fillMaxWidth(), contentAlignment = if (mine) Alignment.CenterEnd else Alignment.CenterStart) {
        Column(
            Modifier.widthIn(max = 290.dp).clip(shape).background(bg).padding(horizontal = Spacing.sm, vertical = Spacing.sm),
        ) {
            if (!mine && showSender) {
                Text(m.senderName.orEmpty(), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(horizontal = 4.dp))
                Spacer(Modifier.height(2.dp))
            }
            m.attachments.orEmpty().forEach { a ->
                AttachmentView(a, fg, accent, context, onOpenImage)
                Spacer(Modifier.height(Spacing.xs))
            }
            if (!m.message.isNullOrBlank()) {
                Text(m.message, style = MaterialTheme.typography.bodyMedium, color = fg, modifier = Modifier.padding(horizontal = 4.dp))
            }
            Text(
                time + if (mine) (if (m.isRead) "  ✓✓" else "  ✓") else "",
                style = MaterialTheme.typography.labelSmall,
                color = fg.copy(alpha = 0.7f),
                modifier = Modifier.align(Alignment.End).padding(horizontal = 4.dp),
            )
        }
    }
}

@Composable
private fun AttachmentView(a: ChatAttachmentDto, fg: Color, accent: Color, context: Context, onOpenImage: (String) -> Unit) {
    val url = a.publicUrl(SessionStore.DEFAULT_BASE_URL)
    when {
        // Foto langsung tampil di gelembung; ketuk untuk memperbesar di dalam aplikasi.
        a.isImage && url != null -> SubcomposeAsyncImage(
            model = url,
            contentDescription = a.originalName ?: "Foto",
            contentScale = ContentScale.Crop,
            loading = {
                Box(Modifier.fillMaxSize().background(fg.copy(alpha = 0.08f)), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(Modifier.size(24.dp), strokeWidth = 2.dp)
                }
            },
            modifier = Modifier.widthIn(min = 200.dp).fillMaxWidth().height(220.dp).clip(RoundedCornerShape(14.dp)).clickable { onOpenImage(url) },
        )
        a.isAudio && url != null -> Box(Modifier.padding(horizontal = 2.dp)) { VoiceNoteView(url, a.durationMs, fg, accent) }
        else -> Row(
            Modifier.clip(RoundedCornerShape(12.dp)).background(fg.copy(alpha = 0.1f)).clickable { if (url != null) openUrl(context, url) }.padding(Spacing.sm),
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

/** Penampil foto layar penuh di dalam aplikasi (cubit untuk zoom, ketuk dua kali untuk reset). */
@Composable
private fun ImageViewer(url: String, onDismiss: () -> Unit) {
    var scale by remember { mutableFloatStateOf(1f) }
    var offset by remember { mutableStateOf(Offset.Zero) }
    val transform = rememberTransformableState { zoom, pan, _ ->
        scale = (scale * zoom).coerceIn(1f, 5f)
        offset = if (scale > 1f) offset + pan else Offset.Zero
    }
    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Box(Modifier.fillMaxSize().background(Color.Black)) {
            AsyncImage(
                model = url,
                contentDescription = "Foto",
                contentScale = ContentScale.Fit,
                modifier = Modifier
                    .fillMaxSize()
                    .transformable(transform)
                    .pointerInput(Unit) { detectTapGestures(onDoubleTap = { scale = 1f; offset = Offset.Zero }) }
                    .graphicsLayer(scaleX = scale, scaleY = scale, translationX = offset.x, translationY = offset.y),
            )
            IconButton(onClick = onDismiss, modifier = Modifier.align(Alignment.TopEnd).padding(Spacing.md)) {
                Icon(Icons.Outlined.Close, contentDescription = "Tutup", tint = Color.White)
            }
        }
    }
}

private fun openUrl(context: Context, url: String) {
    try {
        context.expectExternalActivity()
        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    } catch (_: ActivityNotFoundException) {
        // Tidak ada aplikasi penampil: abaikan.
    }
}
