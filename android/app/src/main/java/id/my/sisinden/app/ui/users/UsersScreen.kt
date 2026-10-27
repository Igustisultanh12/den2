package id.my.sisinden.app.ui.users

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
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.PersonAdd
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.map
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.DropdownField
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.CreateUserRequest
import id.my.sisinden.app.data.model.KnownRoles
import id.my.sisinden.app.data.model.ManagedUserDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel

class UsersViewModel(private val repository: SindenRepository) : LoadableViewModel<List<ManagedUserDto>>() {
    init { load() }

    override suspend fun fetch(): ApiResult<List<ManagedUserDto>> = repository.users().map { it.data.orEmpty() }

    fun setActive(user: ManagedUserDto, active: Boolean) =
        action(if (active) "Akun ${user.name} diaktifkan." else "Akun ${user.name} dinonaktifkan.") {
            repository.setUserActive(user.id, active)
        }

    fun create(request: CreateUserRequest, onDone: () -> Unit) = action("Pengguna dibuat.", onDone) { repository.createUser(request) }

    fun delete(id: Int, onDone: () -> Unit) = action("Pengguna dihapus.", onDone) { repository.deleteUser(id) }
}

private enum class UserFilter(val label: String) { ALL("Semua"), ACTIVE("Aktif"), INACTIVE("Nonaktif") }

@Composable
fun UsersScreen(repository: SindenRepository, permissions: PermissionSet, onBack: () -> Unit) {
    val vm: UsersViewModel = viewModel { UsersViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val canManage = permissions.has(Perm.USERS_MANAGE)

    var query by rememberSaveable { mutableStateOf("") }
    var filter by rememberSaveable { mutableStateOf(UserFilter.ALL) }
    var detail by remember { mutableStateOf<ManagedUserDto?>(null) }
    var creating by rememberSaveable { mutableStateOf(false) }
    var deleting by remember { mutableStateOf<ManagedUserDto?>(null) }
    val selfId = repository.session.user?.id

    SindenScaffold(
        title = "Kelola Pengguna",
        subtitle = "Akun personel Sinden 2",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    onClick = { creating = true },
                    icon = { Icon(Icons.Outlined.PersonAdd, contentDescription = null) },
                    text = { Text("Pengguna") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            SearchField(query, { query = it }, "Cari nama, NRP, email, atau role", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            FilterChipRow(UserFilter.entries, filter, { it.label }, { filter = it })
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { users ->
                    val q = query.trim().lowercase()
                    val list = users.filter { u ->
                        (filter == UserFilter.ALL || (filter == UserFilter.ACTIVE) == u.isActive) &&
                            (q.isEmpty() || listOf(u.name, u.nrp, u.email, u.role).any { it.orEmpty().lowercase().contains(q) })
                    }
                    if (list.isEmpty()) {
                        EmptyState("Tidak ada pengguna", "Tidak ada akun yang cocok dengan pencarian.", icon = Icons.Outlined.Group)
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                        ) {
                            items(list, key = { it.id }) { u ->
                                ListRowCard(
                                    title = u.name.orEmpty(),
                                    subtitle = listOfNotNull(u.pangkat, u.nrp?.let { "NRP $it" }).joinToString(" · "),
                                    supporting = "Role: ${u.role ?: "-"}",
                                    leading = {
                                        InitialsAvatar(u.name.orEmpty().split(" ").take(2).joinToString("") { it.take(1) }, size = 40.dp)
                                    },
                                    trailing = {
                                        if (canManage && u.id != selfId) {
                                            Switch(checked = u.isActive, onCheckedChange = { vm.setActive(u, it) }, enabled = !busy)
                                        } else {
                                            StatusChip(if (u.isActive) "Aktif" else "Nonaktif", if (u.isActive) Tone.SUCCESS else Tone.NEUTRAL)
                                        }
                                    },
                                    onClick = { detail = u },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    detail?.let { u ->
        FormSheet(title = "Detail pengguna", onDismiss = { detail = null }) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                StatusChip(if (u.isActive) "Aktif" else "Nonaktif", if (u.isActive) Tone.SUCCESS else Tone.NEUTRAL)
            }
            Spacer(Modifier.height(Spacing.sm))
            InfoRow("Nama", u.name)
            InfoRow("NRP", u.nrp)
            InfoRow("Pangkat", u.pangkat)
            InfoRow("Jabatan", u.jabatan)
            InfoRow("Email", u.email)
            InfoRow("Telepon", u.phone)
            InfoRow("Role", u.role)
            Text(
                "Pengaturan hak akses lanjutan (akses mitra, kas unit teknis, dll.) dilakukan melalui web Sinden 2.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = Spacing.sm),
            )
            if (canManage && u.id != selfId) {
                Spacer(Modifier.height(Spacing.lg))
                OutlinedButton(onClick = { deleting = u }) {
                    Icon(Icons.Outlined.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Hapus pengguna", color = MaterialTheme.colorScheme.error)
                }
            }
        }
    }

    if (creating) {
        var name by rememberSaveable { mutableStateOf("") }
        var email by rememberSaveable { mutableStateOf("") }
        var nrp by rememberSaveable { mutableStateOf("") }
        var pangkat by rememberSaveable { mutableStateOf("") }
        var role by rememberSaveable { mutableStateOf("personel") }
        var password by rememberSaveable { mutableStateOf("") }
        var errors by remember { mutableStateOf(emptyMap<String, String>()) }
        FormSheet(title = "Pengguna baru", onDismiss = { creating = false }) {
            SindenTextField(name, { name = it }, "Nama lengkap", error = errors["name"])
            SindenTextField(nrp, { nrp = it }, "NRP", error = errors["nrp"])
            SindenTextField(email, { email = it }, "Email", error = errors["email"], keyboardType = KeyboardType.Email)
            SindenTextField(pangkat, { pangkat = it }, "Pangkat (opsional)")
            DropdownField("Role", KnownRoles.all, role, { it }, { role = it })
            SindenTextField(
                password, { password = it }, "Kata sandi awal", error = errors["password"],
                supporting = "Minimal 8 karakter. Minta personel menggantinya setelah masuk.",
                keyboardType = KeyboardType.Password, visualTransformation = PasswordVisualTransformation(),
            )
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Buat akun", loading = busy, onClick = {
                errors = buildMap {
                    if (name.isBlank()) put("name", "Wajib diisi")
                    if (nrp.isBlank()) put("nrp", "Wajib diisi")
                    if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches()) put("email", "Email tidak valid")
                    if (password.length < 8) put("password", "Minimal 8 karakter")
                }
                if (errors.isEmpty()) {
                    vm.create(CreateUserRequest(name.trim(), email.trim(), nrp.trim(), pangkat.trim().ifBlank { null }, role, password)) {
                        creating = false
                    }
                }
            })
        }
    }

    deleting?.let { u ->
        ConfirmDialog(
            title = "Hapus pengguna?",
            message = "Akun ${u.name.orEmpty()} akan dihapus dan seluruh sesi mobile-nya dicabut. Tindakan ini tidak dapat dibatalkan.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { deleting = null },
            onConfirm = { vm.delete(u.id) { deleting = null; detail = null } },
        )
    }
}
