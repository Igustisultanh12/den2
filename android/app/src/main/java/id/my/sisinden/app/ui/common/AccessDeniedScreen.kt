package id.my.sisinden.app.ui.common

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import id.my.sisinden.app.core.ui.components.AccessDeniedState
import id.my.sisinden.app.core.ui.components.SindenScaffold

/** Ditampilkan bila rute dibuka (mis. lewat deep link) tanpa permission. */
@Composable
fun AccessDeniedScreen(onBack: () -> Unit) {
    SindenScaffold(title = "Akses Ditolak", onBack = onBack) { padding ->
        Box(Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
            AccessDeniedState(action = { OutlinedButton(onClick = onBack) { Text("Kembali") } })
        }
    }
}
