# ARSITEKTUR APLIKASI SISINDEN ANDROID NATIVE

Aplikasi **SISINDEN Android** dirancang menggunakan arsitektur modern berstandar industri dengan prinsip **Clean Architecture** dan pola **MVVM (Model-View-ViewModel)** berbasis **Jetpack Compose** dan **Kotlin Coroutines / Flow**.

---

## 1. Diagram Lapisan Arsitektur (Architecture Layers)

```
+-------------------------------------------------------------------+
|                        UI LAYER (Compose)                         |
|  Screens, Components, Theme, Navigation (Navigation Compose)      |
+-------------------------------------------------------------------+
                                  |  UiState (StateFlow)
                                  v  User Intents / Events
+-------------------------------------------------------------------+
|                         VIEWMODEL LAYER                           |
|  AuthViewModel, DashboardViewModel, LetterViewModel, etc.         |
|  State Holders & Business Logic Orchestration                     |
+-------------------------------------------------------------------+
                                  |  Coroutines / Flow
                                  v
+-------------------------------------------------------------------+
|                         DOMAIN / DATA LAYER                       |
|  Repositories: AuthRepository, LetterRepository, CashRepository   |
|  Data Sources: Remote (Retrofit + OkHttp) & Local (DataStore)     |
+-------------------------------------------------------------------+
                                  |
                                  v
+-------------------------------------------------------------------+
|                     NETWORK & STORAGE (Core)                      |
|  Retrofit Service (SindenApiService)                              |
|  SessionManager & Preferences (Jetpack DataStore)                 |
+-------------------------------------------------------------------+
```

---

## 2. Rincian Lapisan & Tanggung Jawab

### 2.1 UI Layer (Jetpack Compose + Material 3)
- **Declarative UI:** Seluruh komponen visual dibangun murni menggunakan fungsi `@Composable`. Tidak menggunakan XML layout lawas.
- **Unidirectional Data Flow (UDF):**
  - ViewModel mengekspos State sebagai `StateFlow<UiState>`.
  - Composable mengamati State secara reaktif menggunakan `collectAsStateWithLifecycle()`.
  - Aksi pengguna (klik, input, scroll) dikirimkan kembali ke ViewModel sebagai event fungsi tanpa merusak immutability state.
- **Sistem Desain Material 3:**
  - `Color.kt`, `Theme.kt`, `Type.kt`: Definisi palet warna editorial militer (Deep Teal `#00A884`, Ocean Blue `#0284C7`, Slate Neutral).
  - Mendukung peralihan mulus antara Light Mode dan Dark Mode.
  - Komponen bersama: `SindenTopBar`, `StatusBadge`, `ShimmerPlaceholder`, `EmptyStateCard`, `ErrorStateView`.

### 2.2 ViewModel Layer
- Mewarisi `androidx.lifecycle.ViewModel`.
- Mengelola siklus hidup data agar tidak hilang saat rotasi layar atau navigasi antar halaman.
- Menangani operasi asinkron dalam lingkup `viewModelScope`.
- Memvalidasi input pengguna sebelum diteruskan ke Repository.

### 2.3 Repository Layer
- Sumber kebenaran data tunggal (*Single Source of Truth*).
- Menyatukan sumber data remote (Retrofit HTTP API) dan penyimpanan lokal (DataStore Preferences).
- Menangani pemetaan DTO (Data Transfer Object) ke model domain UI yang aman (null-safe).

### 2.4 Core Network & Storage
- **Retrofit 2:** Klien HTTP untuk komunikasi dengan server `https://sisinden.my.id`.
- **OkHttp Interceptor:**
  - `AuthInterceptor`: Menyuntikkan header `Authorization: Bearer <TOKEN>` secara otomatis ke setiap permintaan.
  - `HttpLoggingInterceptor`: Logging rapi untuk diagnosa jaringan pada build debug.
- **Jetpack DataStore:**
  - Menyimpan token autentikasi, profil pengguna aktif, dan pengaturan alamat server (Base URL) secara asinkron dan aman dari thread blocking.

---

## 3. Pola State Management (UiState)

Setiap layar menggunakan pola `UiState` bertipe tertutup (Sealed Interface atau Data Class):

```kotlin
sealed interface UiState<out T> {
    data object Idle : UiState<Nothing>
    data object Loading : UiState<Nothing>
    data class Success<T>(val data: T) : UiState<T>
    data class Error(val message: String) : UiState<Nothing>
}
```

Hal ini memastikan setiap kondisi tampilan (Loading, Success, Empty, Error) terisolasi dan tertangani dengan baik pada UI.
