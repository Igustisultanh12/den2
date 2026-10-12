package id.my.sisinden.app.ui.navigation

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.auth.AuthViewModel
import id.my.sisinden.app.ui.cash.CashViewModel
import id.my.sisinden.app.ui.dashboard.DashboardViewModel
import id.my.sisinden.app.ui.letters.LetterViewModel
import id.my.sisinden.app.ui.mitra.MitraViewModel
import id.my.sisinden.app.ui.notifications.NotificationsViewModel
import id.my.sisinden.app.ui.personnel.PersonnelViewModel
import id.my.sisinden.app.ui.settings.SettingsViewModel
import id.my.sisinden.app.ui.signature.SignatureViewModel
import id.my.sisinden.app.ui.skhpp.SkhppViewModel
import id.my.sisinden.app.ui.violations.ViolationsViewModel

class SindenViewModelFactory(private val repository: SindenRepository) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        return when {
            modelClass.isAssignableFrom(AuthViewModel::class.java) ->
                AuthViewModel(repository) as T
            modelClass.isAssignableFrom(DashboardViewModel::class.java) ->
                DashboardViewModel(repository) as T
            modelClass.isAssignableFrom(LetterViewModel::class.java) ->
                LetterViewModel(repository) as T
            modelClass.isAssignableFrom(SkhppViewModel::class.java) ->
                SkhppViewModel(repository) as T
            modelClass.isAssignableFrom(CashViewModel::class.java) ->
                CashViewModel(repository) as T
            modelClass.isAssignableFrom(MitraViewModel::class.java) ->
                MitraViewModel(repository) as T
            modelClass.isAssignableFrom(SignatureViewModel::class.java) ->
                SignatureViewModel(repository) as T
            modelClass.isAssignableFrom(ViolationsViewModel::class.java) ->
                ViolationsViewModel(repository) as T
            modelClass.isAssignableFrom(PersonnelViewModel::class.java) ->
                PersonnelViewModel(repository) as T
            modelClass.isAssignableFrom(NotificationsViewModel::class.java) ->
                NotificationsViewModel(repository) as T
            modelClass.isAssignableFrom(SettingsViewModel::class.java) ->
                SettingsViewModel(repository) as T
            else -> throw IllegalArgumentException("Unknown ViewModel class: ${modelClass.name}")
        }
    }
}
