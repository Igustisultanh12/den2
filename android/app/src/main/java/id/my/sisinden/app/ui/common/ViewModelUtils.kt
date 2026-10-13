package id.my.sisinden.app.ui.common

import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.receiveAsFlow

/**
 * Antrean pesan sekali-tampil (snackbar) dari ViewModel ke UI.
 * Memakai Channel agar pesan tidak terulang saat rotasi layar.
 */
class UiMessages {
    private val channel = Channel<String>(Channel.BUFFERED)
    val flow: Flow<String> = channel.receiveAsFlow()
    fun post(message: String) {
        channel.trySend(message)
    }
}

@Composable
fun CollectMessages(messages: UiMessages, snackbar: SnackbarHostState) {
    LaunchedEffect(messages) {
        messages.flow.collect { snackbar.showSnackbar(it) }
    }
}
