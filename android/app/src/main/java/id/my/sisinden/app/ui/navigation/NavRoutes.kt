package id.my.sisinden.app.ui.navigation

sealed class NavRoute(val route: String) {
    data object Login : NavRoute("login")
    data object Main : NavRoute("main")
    data object LetterLogs : NavRoute("letter_logs")
    data object CreateLetter : NavRoute("create_letter")
    data object Skhpp : NavRoute("skhpp")
    data object CreateSkhpp : NavRoute("create_skhpp")
    data object Cash : NavRoute("cash")
    data object TechnicalCash : NavRoute("technical_cash")
    data object Mitra : NavRoute("mitra")
    data object Signature : NavRoute("signature")
    data object Violations : NavRoute("violations")
    data object Personnel : NavRoute("personnel")
    data object Notifications : NavRoute("notifications")
    data object Settings : NavRoute("settings")
}
