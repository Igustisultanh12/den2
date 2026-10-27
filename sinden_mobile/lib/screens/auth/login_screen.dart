import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  String? _agencyName;
  String? _serverBgUrl;

  @override
  void initState() {
    super.initState();
    _fetchServerConfig();
  }

  Future<void> _fetchServerConfig() async {
    try {
      final config = await ApiService().get('/api/mobile/config');
      if (config != null && config is Map<String, dynamic>) {
        if (mounted) {
          setState(() {
            _agencyName = config['agency_name'] ?? 'DETASEMEN INTELIJEN Koarmada 2';
            _serverBgUrl = config['login_background'];
          });
        }
      }
    } catch (_) {}
  }

  void _showErrorDialog(String title, String message) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        backgroundColor: const Color(0xFF0F172A),
        title: Row(
          children: [
            const Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 28),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: Colors.white),
              ),
            ),
          ],
        ),
        content: Text(
          message,
          style: const TextStyle(fontSize: 13, color: Color(0xFFCBD5E1), height: 1.4),
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('MENGERTI', style: TextStyle(fontWeight: FontWeight.w800, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    final auth = Provider.of<AuthProvider>(context, listen: false);

    final success = await auth.login(
      _usernameController.text.trim(),
      _passwordController.text,
    );

    if (!success && mounted) {
      _showErrorDialog(
        'Gagal Masuk Sistem',
        auth.errorMessage ?? 'NRP / Kata sandi tidak cocok atau server belum dapat dihubungi.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);

    return Scaffold(
      backgroundColor: const Color(0xFF020617), // Deep Slate Navy Web Canvas
      body: Stack(
        children: [
          // Background Image (if configured on server) or Military Gradient
          Positioned.fill(
            child: _serverBgUrl != null
                ? Image.network(
                    _serverBgUrl!,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _buildDefaultGradient(),
                  )
                : _buildDefaultGradient(),
          ),

          // Dark Backdrop Blur Overlay (Identical to Web Login.vue)
          Positioned.fill(
            child: Container(
              color: const Color(0xFF020617).withOpacity(0.85),
            ),
          ),

          // Content
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    // Official Floating Emblem Logo
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.5),
                            blurRadius: 25,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Image.asset(
                        'assets/images/app_logo.png',
                        height: 110,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => const Icon(Icons.shield, size: 90, color: Color(0xFFD97706)),
                      ),
                    ),
                    const SizedBox(height: 14),

                    // App Title & Subtitle
                    Text(
                      _agencyName ?? 'DENINTEL Koarmada 2',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        color: Colors.white,
                        letterSpacing: 1.0,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Sistem Informasi Detasemen Intelijen Terpadu',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Dark Floating Glass Login Card (Web Parity)
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0F172A).withOpacity(0.92),
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: Colors.white.withOpacity(0.12)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.4),
                            blurRadius: 30,
                            offset: const Offset(0, 15),
                          ),
                        ],
                      ),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Masuk Akun',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Gunakan kredensial dinas internal Anda untuk autentikasi.',
                              style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                            ),
                            const SizedBox(height: 20),

                            // Input NRP / Email
                            const Text(
                              'NRP / USERNAME / EMAIL *',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFFCBD5E1), letterSpacing: 0.8),
                            ),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _usernameController,
                              style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                              decoration: InputDecoration(
                                hintText: 'Masukkan NRP atau Email',
                                hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                                prefixIcon: const Icon(Icons.person_outline, color: Color(0xFF94A3B8), size: 20),
                                fillColor: Colors.white.withOpacity(0.06),
                                filled: true,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.white.withOpacity(0.15))),
                                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.white.withOpacity(0.15))),
                                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFF3B82F6), width: 2)),
                              ),
                              validator: (val) => val == null || val.trim().isEmpty ? 'NRP atau Email wajib diisi' : null,
                            ),
                            const SizedBox(height: 16),

                            // Input Password
                            const Text(
                              'KATA SANDI *',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFFCBD5E1), letterSpacing: 0.8),
                            ),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                              decoration: InputDecoration(
                                hintText: 'Masukkan kata sandi',
                                hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                                prefixIcon: const Icon(Icons.lock_outline, color: Color(0xFF94A3B8), size: 20),
                                suffixIcon: IconButton(
                                  icon: Icon(_obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: const Color(0xFF94A3B8), size: 20),
                                  onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                ),
                                fillColor: Colors.white.withOpacity(0.06),
                                filled: true,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.white.withOpacity(0.15))),
                                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.white.withOpacity(0.15))),
                                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFF3B82F6), width: 2)),
                              ),
                              validator: (val) => val == null || val.isEmpty ? 'Kata sandi wajib diisi' : null,
                            ),
                            const SizedBox(height: 14),

                            // GPS Auto Verification Indicator
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                              decoration: BoxDecoration(
                                color: const Color(0xFF064E3B).withOpacity(0.6),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: const Color(0xFF059669).withOpacity(0.4)),
                              ),
                              child: const Row(
                                children: [
                                  Icon(Icons.gps_fixed, size: 16, color: Color(0xFF34D399)),
                                  SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'GPS Positioning Kedinasan Aktif Terverifikasi',
                                      style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFF34D399),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 20),

                            // Submit Button
                            SizedBox(
                              width: double.infinity,
                              height: 48,
                              child: ElevatedButton(
                                onPressed: auth.isLoading ? null : _handleLogin,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF1E3A8A),
                                  foregroundColor: Colors.white,
                                  elevation: 4,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                ),
                                child: auth.isLoading
                                    ? const SizedBox(
                                        height: 20,
                                        width: 20,
                                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5, strokeCap: StrokeCap.round),
                                      )
                                    : const Text(
                                        'MASUK KE SISTEM',
                                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 0.8),
                                      ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Footer
                    Text(
                      'Â© ${DateTime.now().year} ${_agencyName ?? 'SI SINDEN'}. All Rights Reserved.',
                      style: const TextStyle(fontSize: 10, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDefaultGradient() {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF020617), Color(0xFF0F172A), Color(0xFF1E293B)],
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
        ),
      ),
    );
  }
}