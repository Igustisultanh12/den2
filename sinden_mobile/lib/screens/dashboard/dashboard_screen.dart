import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/location_provider.dart';
import '../../providers/notification_provider.dart';
import '../notifications/notifications_screen.dart';
import '../tracking/personnel_map_screen.dart';
import '../letter_logs/letter_logs_screen.dart';
import '../letters/letters_draft_screen.dart';
import '../categories/categories_screen.dart';
import '../skhpp/skhpp_list_screen.dart';
import '../commander/commander_account_screen.dart';
import '../mitra/mitra_screen.dart';
import '../violations/violations_screen.dart';
import '../signature/signature_screen.dart';
import '../users/users_screen.dart';
import '../audit/audit_logs_screen.dart';
import '../settings/settings_screen.dart';
import '../backup/backup_screen.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  void _showAccessDeniedDialog(BuildContext context, String menuName) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.lock_outline, color: Color(0xFFDC2626), size: 26),
            SizedBox(width: 10),
            Text('Akses Dibatasi', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ],
        ),
        content: Text(
          'Fitur "$menuName" memerlukan izin khusus dari Admin / Komandan SINDEN. Silakan hubungi Admin untuk membuka matriks otoritas akun Anda.',
          style: const TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4),
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0F172A),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('MENGERTI', style: TextStyle(fontWeight: FontWeight.w800, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final loc = Provider.of<LocationProvider>(context);
    final notif = Provider.of<NotificationProvider>(context);
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'SI SINDEN',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: AppTheme.primaryNavy),
            ),
            Text(
              user?.jabatan ?? 'Detasemen Intelijen Koarmada 2',
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: AppTheme.secondaryGold),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Badge(
              isLabelVisible: notif.unreadCount > 0,
              label: Text('${notif.unreadCount}'),
              child: const Icon(Icons.notifications_outlined),
            ),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const NotificationsScreen()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.map_outlined),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const PersonnelMapScreen()),
              );
            },
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await notif.fetchNotifications();
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // User Header Profile Card
            Card(
              color: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: const BorderSide(color: Color(0xFFE2E8F0))),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: AppTheme.primaryNavy.withOpacity(0.1),
                      child: const Icon(Icons.person, size: 32, color: AppTheme.primaryNavy),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: user?.isAdmin == true
                                      ? const Color(0xFFDC2626)
                                      : (user?.isKomandan == true ? const Color(0xFF7C3AED) : const Color(0xFF2563EB)),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  user?.role?.toUpperCase() ?? 'PERSONEL',
                                  style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Colors.white),
                                ),
                              ),
                              const SizedBox(width: 6),
                              if (user?.pangkat != null)
                                Text(
                                  user!.pangkat!,
                                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                                ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            user?.name ?? 'Personel SINDEN',
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            user?.nrp != null ? 'NRP: ${user!.nrp}' : 'Denintel Koarmada 2',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),

            // GPS Tracking Card
            Card(
              color: loc.isTrackingEnabled ? const Color(0xFFF0FDF4) : const Color(0xFFFEF2F2),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(
                  color: loc.isTrackingEnabled ? const Color(0xFFBBF7D0) : const Color(0xFFFECACA),
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                child: Row(
                  children: [
                    Icon(
                      loc.isTrackingEnabled ? Icons.gps_fixed : Icons.gps_off,
                      color: loc.isTrackingEnabled ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                      size: 22,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            loc.isTrackingEnabled ? 'PELACAKAN GPS DUDUKAN DINAS AKTIF' : 'GPS POSITIONING OFF',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                              color: loc.isTrackingEnabled ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                            ),
                          ),
                          Text(
                            loc.isTrackingEnabled ? 'Posisi dinas terdeteksi & terkirim ke Komando' : 'Aktifkan GPS untuk pelaporan posisi',
                            style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                    Switch(
                      value: loc.isTrackingEnabled,
                      activeColor: const Color(0xFF16A34A),
                      onChanged: (_) => loc.toggleTracking(),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // CATEGORY 1: SURAT & NASKAH
            _buildCategoryHeader('SURAT & NASKAH DINAS'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Agenda Surat',
                  icon: Icons.mark_email_read_outlined,
                  color: const Color(0xFFD97706),
                  hasAccess: user?.canAccessAgenda ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LetterLogsScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Buat & Draf',
                  icon: Icons.edit_note_outlined,
                  color: const Color(0xFFB45309),
                  hasAccess: user?.canAccessLetters ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LettersDraftScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Cetak SKHPP',
                  icon: Icons.verified_user_outlined,
                  color: const Color(0xFF16A34A),
                  hasAccess: user?.canAccessSkhpp ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SkhppListScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Kategori Surat',
                  icon: Icons.category_outlined,
                  color: const Color(0xFF0284C7),
                  hasAccess: user?.canAccessCategories ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CategoriesScreen())),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 2: VALIDASI & TTE
            _buildCategoryHeader('VALIDASI & TANDA TANGAN DIGITAL'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'TTE Digital',
                  icon: Icons.draw_outlined,
                  color: const Color(0xFF7C3AED),
                  hasAccess: user?.canAccessSignature ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SignatureScreen())),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 3: LOGISTIK & KEUANGAN
            _buildCategoryHeader('LOGISTIK & KEUANGAN UNIT'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Rekening Dan',
                  icon: Icons.credit_card_outlined,
                  color: const Color(0xFF047857),
                  hasAccess: user?.canAccessCommander ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CommanderAccountScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Catatan Mitra',
                  icon: Icons.handshake_outlined,
                  color: const Color(0xFF2563EB),
                  hasAccess: user?.canAccessMitra ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const MitraScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Backup Data',
                  icon: Icons.backup_outlined,
                  color: const Color(0xFF10B981),
                  hasAccess: user?.isAdmin ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const BackupScreen())),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 4: PENGAMANAN & OPR
            _buildCategoryHeader('PENGAMANAN & RADAR LAPANGAN'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Pelanggaran',
                  icon: Icons.gavel_outlined,
                  color: const Color(0xFFE11D48),
                  hasAccess: user?.canAccessViolations ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ViolationsScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Radar Kegiatan',
                  icon: Icons.radar_outlined,
                  color: const Color(0xFF3B82F6),
                  hasAccess: user?.canAccessActivities ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PersonnelMapScreen())),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 5: SISTEM & OTORITAS ADMIN
            _buildCategoryHeader('SISTEM & OTORITAS ADMIN'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Kelola Pengguna',
                  icon: Icons.people_alt_outlined,
                  color: const Color(0xFF475569),
                  hasAccess: user?.isAdmin ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const UsersScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Log & Audit',
                  icon: Icons.manage_history_outlined,
                  color: const Color(0xFF64748B),
                  hasAccess: user?.isAdmin == true || user?.isKomandan == true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AuditLogsScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Pengaturan',
                  icon: Icons.settings_outlined,
                  color: const Color(0xFF334155),
                  hasAccess: user?.isAdmin ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SettingsScreen())),
                ),
              ],
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        title,
        style: const TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w900,
          color: Color(0xFF64748B),
          letterSpacing: 0.8,
        ),
      ),
    );
  }

  Widget _buildMenuItem({
    required BuildContext context,
    required String title,
    required IconData icon,
    required Color color,
    required bool hasAccess,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: hasAccess ? onTap : () => _showAccessDeniedDialog(context, title),
        borderRadius: BorderRadius.circular(20),
        splashColor: color.withOpacity(0.15),
        highlightColor: color.withOpacity(0.08),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOutCubic,
          decoration: BoxDecoration(
            color: hasAccess ? Colors.white : const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: hasAccess ? const Color(0xFFE2E8F0) : const Color(0xFFF1F5F9),
              width: 1,
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Stack(
                alignment: Alignment.center,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeOutCubic,
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: hasAccess ? color.withOpacity(0.12) : const Color(0xFFE2E8F0),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      icon,
                      size: 22,
                      color: hasAccess ? color : const Color(0xFF94A3B8),
                    ),
                  ),
                  if (!hasAccess)
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        decoration: const BoxDecoration(
                          color: Color(0xFFEF4444),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.lock, size: 10, color: Colors.white),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                title,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: hasAccess ? FontWeight.w800 : FontWeight.w600,
                  color: hasAccess ? const Color(0xFF1E293B) : const Color(0xFF94A3B8),
                  height: 1.15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}