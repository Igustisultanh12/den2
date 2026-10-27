package id.my.sisinden.app.core.security

import android.content.Context
import android.content.pm.PackageManager
import android.hardware.biometrics.BiometricManager
import android.hardware.biometrics.BiometricPrompt
import android.os.Build
import android.os.CancellationSignal

/**
 * Login biometrik (sidik jari / pengenalan wajah) memakai BiometricPrompt
 * bawaan Android 9+. Biometrik hanya membuka kunci aplikasi pada perangkat
 * ini — data biometrik tidak pernah dibaca atau dikirim oleh aplikasi.
 */
object Biometric {

    /** Perangkat mendukung & sudah ada sidik jari/wajah yang terdaftar. */
    fun isAvailable(context: Context): Boolean = when {
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.R -> {
            val bm = context.getSystemService(BiometricManager::class.java)
            bm?.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK) == BiometricManager.BIOMETRIC_SUCCESS
        }
        Build.VERSION.SDK_INT == Build.VERSION_CODES.Q -> {
            val bm = context.getSystemService(BiometricManager::class.java)
            @Suppress("DEPRECATION")
            bm?.canAuthenticate() == BiometricManager.BIOMETRIC_SUCCESS
        }
        Build.VERSION.SDK_INT == Build.VERSION_CODES.P ->
            context.packageManager.hasSystemFeature(PackageManager.FEATURE_FINGERPRINT)
        else -> false
    }

    /**
     * Tampilkan dialog biometrik sistem.
     * @param onError dipanggil dengan pesan bila gagal permanen (mis. terkunci sementara);
     *   pembatalan oleh pengguna tidak dianggap error.
     */
    fun authenticate(
        context: Context,
        title: String,
        subtitle: String,
        onSuccess: () -> Unit,
        onError: (String) -> Unit,
    ): CancellationSignal? {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P) return null
        val executor = context.mainExecutor
        val builder = BiometricPrompt.Builder(context)
            .setTitle(title)
            .setSubtitle(subtitle)
            .setNegativeButton("Gunakan PIN", executor) { _, _ -> }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) builder.setConfirmationRequired(false)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            builder.setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_WEAK)
        }
        val signal = CancellationSignal()
        return try {
            builder.build().authenticate(
                signal,
                executor,
                object : BiometricPrompt.AuthenticationCallback() {
                    override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) = onSuccess()

                    override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                        // Tombol "Gunakan PIN" ditangani listener tombol negatif (bukan error).
                        val silent = errorCode == BiometricPrompt.BIOMETRIC_ERROR_USER_CANCELED ||
                            errorCode == BiometricPrompt.BIOMETRIC_ERROR_CANCELED
                        if (!silent) onError(errString.toString().ifBlank { "Biometrik tidak dapat digunakan. Gunakan PIN." })
                    }
                },
            )
            signal
        } catch (e: RuntimeException) {
            onError("Biometrik tidak tersedia di perangkat ini. Gunakan PIN.")
            null
        }
    }
}
