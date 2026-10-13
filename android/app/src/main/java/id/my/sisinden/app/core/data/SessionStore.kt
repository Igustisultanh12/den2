package id.my.sisinden.app.core.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.gson.Gson
import id.my.sisinden.app.core.ui.theme.ThemeMode
import id.my.sisinden.app.data.model.UserProfile
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

// Nama file DataStore dipertahankan agar sesi pengguna v1.0.x tetap terbaca.
private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "sinden_preferences")

data class StoredSession(val token: String, val user: UserProfile)

/**
 * Penyimpanan lokal kecil: token sesi, profil terakhir (cache permission untuk
 * mode luring), URL server, dan preferensi tampilan. Berkas DataStore berada di
 * penyimpanan privat aplikasi dan dikecualikan dari backup (lihat manifest).
 */
class SessionStore(private val context: Context) {

    private val gson = Gson()

    private object Keys {
        val TOKEN = stringPreferencesKey("auth_bearer_token")
        val USER = stringPreferencesKey("auth_user_json")
        val BASE_URL = stringPreferencesKey("custom_base_url")
        val THEME = stringPreferencesKey("pref_theme_mode")
        val LEGACY_DARK = booleanPreferencesKey("pref_dark_mode")
        val HIDE_BALANCE = booleanPreferencesKey("pref_hide_balance")
    }

    companion object {
        const val DEFAULT_BASE_URL = "https://sisinden.my.id"

        /** Normalisasi & validasi URL server. Hanya HTTPS yang diizinkan. */
        fun normalizeBaseUrl(input: String): String? {
            var url = input.trim()
            if (url.isEmpty()) return null
            if (!url.startsWith("http://") && !url.startsWith("https://")) url = "https://$url"
            if (!url.startsWith("https://")) return null
            url = url.trimEnd('/')
            val host = url.removePrefix("https://").substringBefore('/')
            if (host.isBlank() || host.contains(' ') || !host.contains('.')) return null
            return url
        }
    }

    val baseUrlFlow: Flow<String> = context.dataStore.data.map { it[Keys.BASE_URL] ?: DEFAULT_BASE_URL }

    val themeModeFlow: Flow<ThemeMode> = context.dataStore.data.map { prefs ->
        prefs[Keys.THEME]?.let { runCatching { ThemeMode.valueOf(it) }.getOrNull() }
            ?: when (prefs[Keys.LEGACY_DARK]) { // migrasi preferensi v1.0.x
                true -> ThemeMode.DARK
                false -> ThemeMode.LIGHT
                null -> ThemeMode.SYSTEM
            }
    }

    val hideBalanceFlow: Flow<Boolean> = context.dataStore.data.map { it[Keys.HIDE_BALANCE] ?: false }

    suspend fun readSession(): StoredSession? {
        val prefs = context.dataStore.data.first()
        val token = prefs[Keys.TOKEN]?.takeIf { it.isNotBlank() } ?: return null
        val user = prefs[Keys.USER]?.let { json ->
            runCatching { gson.fromJson(json, UserProfile::class.java) }.getOrNull()
        } ?: return null
        return StoredSession(token, user)
    }

    suspend fun saveSession(token: String, user: UserProfile) {
        context.dataStore.edit {
            it[Keys.TOKEN] = token
            it[Keys.USER] = gson.toJson(user)
        }
    }

    suspend fun updateUser(user: UserProfile) {
        context.dataStore.edit { it[Keys.USER] = gson.toJson(user) }
    }

    suspend fun clearSession() {
        context.dataStore.edit {
            it.remove(Keys.TOKEN)
            it.remove(Keys.USER)
        }
    }

    suspend fun baseUrl(): String = baseUrlFlow.first()

    suspend fun setBaseUrl(url: String) {
        context.dataStore.edit { it[Keys.BASE_URL] = url }
    }

    suspend fun setThemeMode(mode: ThemeMode) {
        context.dataStore.edit {
            it[Keys.THEME] = mode.name
            it.remove(Keys.LEGACY_DARK)
        }
    }

    suspend fun setHideBalance(hide: Boolean) {
        context.dataStore.edit { it[Keys.HIDE_BALANCE] = hide }
    }
}
