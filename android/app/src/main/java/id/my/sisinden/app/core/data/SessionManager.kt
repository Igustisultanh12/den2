package id.my.sisinden.app.core.data

import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.data.model.UserProfile
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

/** Alasan sesi berakhir, untuk pesan di layar login. */
enum class LogoutReason { USER, EXPIRED, DISABLED }

sealed interface SessionState {
    data object Restoring : SessionState
    data class LoggedOut(val reason: LogoutReason? = null) : SessionState
    data class LoggedIn(
        val user: UserProfile,
        val permissions: PermissionSet,
        /** true bila permission berasal dari cache lokal karena server belum terjangkau. */
        val isStale: Boolean = false,
    ) : SessionState
}

/**
 * Sumber tunggal status sesi di aplikasi. Token disimpan di memori untuk
 * interceptor (tanpa runBlocking per-request) dan di DataStore untuk persistensi.
 */
class SessionManager(
    private val store: SessionStore,
    private val scope: CoroutineScope,
) {
    private val _state = MutableStateFlow<SessionState>(SessionState.Restoring)
    val state: StateFlow<SessionState> = _state.asStateFlow()

    private val mutex = Mutex()

    @Volatile
    var token: String? = null
        private set

    val permissions: PermissionSet
        get() = (state.value as? SessionState.LoggedIn)?.permissions ?: PermissionSet.EMPTY

    val user: UserProfile?
        get() = (state.value as? SessionState.LoggedIn)?.user

    /** Muat sesi tersimpan. Status `isStale` sampai server mengonfirmasi lewat [updateUser]. */
    suspend fun restore(): Boolean = mutex.withLock {
        val stored = store.readSession()
        if (stored == null) {
            token = null
            _state.value = SessionState.LoggedOut()
            false
        } else {
            token = stored.token
            _state.value = SessionState.LoggedIn(stored.user, stored.user.permissionSet(), isStale = true)
            true
        }
    }

    suspend fun startSession(newToken: String, user: UserProfile) = mutex.withLock {
        store.saveSession(newToken, user)
        token = newToken
        _state.value = SessionState.LoggedIn(user, user.permissionSet(), isStale = false)
    }

    /** Perbarui profil & permission dari server (mis. setelah admin mengubah hak akses). */
    suspend fun updateUser(user: UserProfile) = mutex.withLock {
        if (token == null) return@withLock
        store.updateUser(user)
        _state.value = SessionState.LoggedIn(user, user.permissionSet(), isStale = false)
    }

    suspend fun end(reason: LogoutReason) = mutex.withLock {
        token = null
        store.clearSession()
        _state.value = SessionState.LoggedOut(reason)
    }

    /** Dipanggil interceptor saat server menolak token (401). */
    fun onUnauthorized() {
        if (token == null) return
        scope.launch { end(LogoutReason.EXPIRED) }
    }

    /** Dipanggil saat server menyatakan akun dinonaktifkan (403 account_inactive). */
    fun onAccountDisabled() {
        if (token == null) return
        scope.launch { end(LogoutReason.DISABLED) }
    }
}
