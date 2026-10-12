package id.my.sisinden.app.ui.skhpp

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.components.SindenTopBar

@Composable
fun CreateSkhppScreen(
    viewModel: SkhppViewModel,
    onBackClick: () -> Unit
) {
    var name by remember { mutableStateOf("") }
    var nrp by remember { mutableStateOf("") }
    var pangkat by remember { mutableStateOf("") }
    var purpose by remember { mutableStateOf("") }

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Pengajuan SKHPP",
                subtitle = "Permohonan Penelitian Personel Baru",
                onBackClick = onBackClick
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Card(
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    Text(
                        text = "Identitas Personel Pemohon",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )

                    OutlinedTextField(
                        value = name,
                        onValueChange = { name = it },
                        label = { Text("Nama Lengkap Personel *") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier.fillMaxWidth()
                    )

                    OutlinedTextField(
                        value = nrp,
                        onValueChange = { nrp = it },
                        label = { Text("NRP Personel *") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier.fillMaxWidth()
                    )

                    OutlinedTextField(
                        value = pangkat,
                        onValueChange = { pangkat = it },
                        label = { Text("Pangkat / Korps") },
                        placeholder = { Text("Contoh: Sersan Satu KOM") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier.fillMaxWidth()
                    )

                    OutlinedTextField(
                        value = purpose,
                        onValueChange = { purpose = it },
                        label = { Text("Keperluan Pengajuan SKHPP *") },
                        placeholder = { Text("Contoh: Persyaratan UKP Periode 01-10-2026") },
                        minLines = 3,
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier.fillMaxWidth()
                    )

                    Spacer(modifier = Modifier.height(6.dp))

                    Button(
                        onClick = {
                            if (name.isNotEmpty() && nrp.isNotEmpty() && purpose.isNotEmpty()) {
                                viewModel.createSkhpp(name, nrp, pangkat, purpose, onSuccess = onBackClick)
                            }
                        },
                        enabled = name.isNotBlank() && nrp.isNotBlank() && purpose.isNotBlank(),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp)
                    ) {
                        Text("KIRIM PENGAJUAN SKHPP", fontWeight = FontWeight.Black)
                    }
                }
            }
        }
    }
}
