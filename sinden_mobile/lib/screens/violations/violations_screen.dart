import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class ViolationsScreen extends StatefulWidget {
  const ViolationsScreen({super.key});

  @override
  State<ViolationsScreen> createState() => _ViolationsScreenState();
}

class _ViolationsScreenState extends State<ViolationsScreen> {
  bool _isLoading = true;
  List<dynamic> _violations = [];
  Map<String, dynamic> _stats = {};
  String _search = '';

  @override
  void initState() {
    super.initState();
    _fetchViolations();
  }

  Future<void> _fetchViolations() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/violations?search=$_search');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _violations = res['data'] ?? res['violations'] ?? [];
          _stats = res['stats'] ?? {};
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _showDetailAndProgressDialog(dynamic v) {
    final noteController = TextEditingController();
    String currentStatus = v['status'] ?? 'PROSES';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: Text('Detail & Riwayat Kasus Prajurit', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          content: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(v['name'] ?? v['nama'] ?? '-', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15, color: AppTheme.primaryNavy)),
                Text('${v['pangkat'] ?? ''} NRP ${v['nrp'] ?? '-'} (${v['unit'] ?? 'Denintel'})', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                const Divider(height: 20),
                const Text('Uraian Kasus:', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B))),
                Text(v['case_description'] ?? v['kasus'] ?? '-', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                const Text('Riwayat Perkembangan Kasus:', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B))),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE2E8F0))),
                  child: Text(v['case_progress'] ?? 'Dalam pemeriksaan awal.', style: const TextStyle(fontSize: 11, height: 1.4)),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  value: currentStatus,
                  decoration: InputDecoration(labelText: 'Ubah Status Kasus', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  items: const [
                    DropdownMenuItem(value: 'PROSES', child: Text('DALAM PROSES PEMERIKSAAN')),
                    DropdownMenuItem(value: 'SIDANG', child: Text('SIDANG PERKARA')),
                    DropdownMenuItem(value: 'SELESAI', child: Text('SELESAI / EKSEKUSI')),
                    DropdownMenuItem(value: 'DINAS_KEMBALI', child: Text('DINAS KEMBALI')),
                  ],
                  onChanged: (val) => setModalState(() => currentStatus = val!),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: noteController,
                  maxLines: 2,
                  decoration: InputDecoration(labelText: 'Catatan Perkembangan Baru', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('TUTUP')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFDC2626)),
              onPressed: () async {
                final id = v['id'];
                if (id != null) {
                  await ApiService().post('/api/mobile/violations/$id/progress', {
                    'note': noteController.text.trim(),
                    'status': currentStatus,
                  });
                  Navigator.of(ctx).pop();
                  _fetchViolations();
                }
              },
              child: const Text('SIMPAN PERUBAHAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _showAddViolationDialog() {
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController();
    final nrpController = TextEditingController();
    final pangkatController = TextEditingController(text: 'Prajurit');
    final kasusController = TextEditingController();
    final unitController = TextEditingController(text: 'Denintel Koarmada 2');
    String status = 'PROSES';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: const Text('Catat Pelanggaran Prajurit', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextFormField(
                    controller: nameController,
                    decoration: InputDecoration(labelText: 'Nama Lengkap Prajurit', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Nama wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: nrpController,
                    decoration: InputDecoration(labelText: 'NRP', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'NRP wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: pangkatController,
                    decoration: InputDecoration(labelText: 'Pangkat / Korps', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: unitController,
                    decoration: InputDecoration(labelText: 'Satuan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: kasusController,
                    maxLines: 2,
                    decoration: InputDecoration(labelText: 'Uraian Kasus / Pelanggaran', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Uraian kasus wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: status,
                    decoration: InputDecoration(labelText: 'Status Kasus', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    items: const [
                      DropdownMenuItem(value: 'PROSES', child: Text('DALAM PROSES PEMERIKSAAN')),
                      DropdownMenuItem(value: 'SIDANG', child: Text('SIDANG PERKARA')),
                      DropdownMenuItem(value: 'SELESAI', child: Text('SELESAI / SIDANG DISIPLIN')),
                    ],
                    onChanged: (v) => setModalState(() => status = v!),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFDC2626), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              onPressed: () async {
                if (formKey.currentState!.validate()) {
                  await ApiService().post('/api/mobile/violations', {
                    'name': nameController.text.trim(),
                    'nrp': nrpController.text.trim(),
                    'pangkat': pangkatController.text.trim(),
                    'unit': unitController.text.trim(),
                    'case_description': kasusController.text.trim(),
                    'status': status,
                  });
                  Navigator.of(ctx).pop();
                  _fetchViolations();
                }
              },
              child: const Text('SIMPAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _deleteViolation(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus Catatan Pelanggaran?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Data pelanggaran prajurit ini akan dihapus permanen.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('BATAL')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFDC2626)),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('HAPUS', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await ApiService().delete('/api/mobile/violations/$id');
      _fetchViolations();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Catatan Pelanggaran', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchViolations),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFFDC2626),
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('CATAT PELANGGARAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 0.5)),
        onPressed: _showAddViolationDialog,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchViolations,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(16)),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('TOTAL KASUS', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFF64748B))),
                              const SizedBox(height: 4),
                              Text('${_stats['total'] ?? _violations.length}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(16)),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('PROSES', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFFDC2626))),
                              const SizedBox(height: 4),
                              Text('${_stats['proses'] ?? 0}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFFDC2626))),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(16)),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('SELESAI', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFF16A34A))),
                              const SizedBox(height: 4),
                              Text('${_stats['selesai'] ?? 0}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF16A34A))),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (_violations.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Text('Tidak ada catatan pelanggaran prajurit.', style: TextStyle(color: Color(0xFF64748B))),
                    )
                  else
                    ..._violations.map((v) {
                      final isProses = v['status'] == 'PROSES';
                      final id = v['id'];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                        child: InkWell(
                          onTap: () => _showDetailAndProgressDialog(v),
                          borderRadius: BorderRadius.circular(18),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Text(v['name'] ?? v['nama'] ?? '-', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: isProses ? const Color(0xFFFEE2E2) : const Color(0xFFDCFCE7),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        v['status'] ?? 'PROSES',
                                        style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: isProses ? const Color(0xFFDC2626) : const Color(0xFF16A34A)),
                                      ),
                                    ),
                                    if (id != null)
                                      IconButton(
                                        icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626), size: 20),
                                        onPressed: () => _deleteViolation(id),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text('${v['pangkat'] ?? ''} NRP ${v['nrp'] ?? '-'} (${v['unit'] ?? 'Denintel'})', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
                                const Divider(height: 16),
                                Text('Kasus: ${v['case_description'] ?? v['kasus'] ?? '-'}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF334155))),
                                const SizedBox(height: 6),
                                const Text('Tekan kartu untuk melihat detail & update kasus ->', style: TextStyle(fontSize: 10, color: Color(0xFF2563EB), fontWeight: FontWeight.bold)),
                              ],
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  const SizedBox(height: 80),
                ],
              ),
            ),
    );
  }
}