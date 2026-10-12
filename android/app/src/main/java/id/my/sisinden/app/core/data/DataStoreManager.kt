package id.my.sisinden.app.core.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.gson.Gson
import id.my.sisinden.app.data.model.UserDto
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "sinden_preferences")

class DataStoreManager(private val context: Context) {
    private val gson = Gson()

    companion object {
        val KEY_TOKEN = stringPreferencesKey("auth_bearer_token")
        val KEY_USER = stringPreferencesKey("auth_user_json")
        val KEY_BASE_URL = stringPreferencesKey("custom_base_url")
        val KEY_DARK_MODE = booleanPreferencesKey("pref_dark_mode")
        const val DEFAULT_BASE_URL = "https://sisinden.my.id"
    }

    val tokenFlow: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_TOKEN]
    }

    val userFlow: Flow<UserDto?> = context.dataStore.data.map { preferences ->
        val json = preferences[KEY_USER]
        if (!json.isNullOrEmpty()) {
            try {
                gson.fromJson(json, UserDto::class.java)
            } catch (e: Exception) {
                null
            }
        } else {
            null
        }
    }

    val baseUrlFlow: Flow<String> = context.dataStore.data.map { preferences ->
        preferences[KEY_BASE_URL] ?: DEFAULT_BASE_URL
    }

    val isDarkModeFlow: Flow<Boolean?> = context.dataStore.data.map { preferences ->
        preferences[KEY_DARK_MODE]
    }

    suspend fun saveAuth(token: String, user: UserDto) {
        context.dataStore.edit { preferences ->
            preferences[KEY_TOKEN] = token
            preferences[KEY_USER] = gson.toJson(user)
        }
    }

    suspend fun clearAuth() {
        context.dataStore.edit { preferences ->
            preferences.remove(KEY_TOKEN)
            preferences.remove(KEY_USER)
        }
    }

    suspend fun setBaseUrl(url: String) {
        var cleanUrl = url.trim()
        if (!cleanUrl.startsWith("http://") && !cleanUrl.startsWith("https://")) {
            cleanUrl = "https://$cleanUrl"
        }
        if (cleanUrl.endsWith("/")) {
            cleanUrl = cleanUrl.substring(0, cleanUrl.length - 1)
        }
        context.dataStore.edit { preferences ->
            preferences[KEY_BASE_URL] = cleanUrl
        }
    }

    suspend fun setDarkMode(enabled: Boolean) {
        context.dataStore.edit { preferences ->
            preferences[KEY_DARK_MODE] = enabled
        }
    }

    suspend fun getToken(): String? {
        return context.dataStore.data.first()[KEY_TOKEN]
    }

    suspend fun getBaseUrl(): String {
        return context.dataStore.data.first()[KEY_BASE_URL] ?: DEFAULT_BASE_URL
    }
}
