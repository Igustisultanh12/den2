import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../core/theme.dart';

class PdfPreviewScreen extends StatelessWidget {
  final String title;
  final String documentNumber;
  final String? date;
  final String? recipient;
  final String? subject;
  final String? contentText;
  final String? verificationCode;
  final String? signerName;
  final String? pdfUrl;

  const PdfPreviewScreen({
    super.key,
    required this.title,
    required this.documentNumber,
    this.date,
    this.recipient,
    this.subject,
    this.contentText,
    this.verificationCode,
    this.signerName,
    this.pdfUrl,
  });

  @override
  Widget build(BuildContext context) {
    final vCode = verificationCode ?? 'DOC-${documentNumber.replaceAll(RegExp(r'[^a-zA-Z0-9]'), '')}';
    final verifyUrl = 'https://sisinden.my.id/verify-signature/$vCode';

    return Scaffold(
      backgroundColor: const Color(0xFF334155),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F172A),
        foregroundColor: Colors.white,
        title: Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Tautan dokumen kedinasan disalin.')),
              );
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        child: Center(
          child: Container(
            constraints: const BoxConstraints(maxWidth: 600),
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              boxShadow: [
                BoxShadow(color: Colors.black.withOpacity(0.3), blurRadius: 20, offset: const Offset(0, 10))
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Kop Surat Resmi TNI AL
                Center(
                  child: Column(
                    children: [
                      const Text(
                        'TENTARA NASIONAL INDONESIA ANGKATAN LAUT',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.8),
                      ),
                      const Text(
                        'DETASEMEN INTELIJEN Koarmada 2',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900, letterSpacing: 1.0, color: AppTheme.primaryNavy),
                      ),
                      const SizedBox(height: 6),
                      Container(height: 2, color: Colors.black),
                      const SizedBox(height: 2),
                      Container(height: 1, color: Colors.black),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Judul Dokumen & Nomor
                Center(
                  child: Column(
                    children: [
                      Text(
                        title.toUpperCase(),
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, decoration: TextDecoration.underline),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Nomor: $documentNumber',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF334155)),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),

                // Meta Info
                if (recipient != null) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(width: 80, child: Text('Kepada', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold))),
                      const Text(': '),
                      Expanded(child: Text(recipient!, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
                    ],
                  ),
                  const SizedBox(height: 6),
                ],
                if (subject != null) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(width: 80, child: Text('Perihal', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold))),
                      const Text(': '),
                      Expanded(child: Text(subject!, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800))),
                    ],
                  ),
                  const SizedBox(height: 6),
                ],
                if (date != null) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(width: 80, child: Text('Tanggal', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold))),
                      const Text(': '),
                      Expanded(child: Text(date!, style: const TextStyle(fontSize: 11))),
                    ],
                  ),
                  const SizedBox(height: 14),
                ],

                const Divider(height: 20),

                // Isi Naskah Dinas
                Text(
                  contentText ??
                      '1. Dasar:\n    a. Program Kerja dan Anggaran Detasemen Intelijen Koarmada 2 TA 2026.\n    b. Petunjuk Perintah Komando Atas terkait Administrasi Kedinasan Terpadu.\n\n2. Sehubungan dengan dasar tersebut di atas, disampaikan kepada alamat tujuan naskah dinas resmi ini untuk diketahui, dipedomani, dan dilaksanakan sebagaimana mestinya.\n\n3. Demikian naskah dinas ini dibuat untuk dapat dipergunakan sebagaimana mestinya.',
                  style: const TextStyle(fontSize: 11, height: 1.6, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 32),

                // Tanda Tangan & QR Code Block Resmi
                Align(
                  alignment: Alignment.centerRight,
                  child: Container(
                    width: 240,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        const Text('Dikeluarkan di Surabaya', style: TextStyle(fontSize: 9, color: Color(0xFF64748B))),
                        Text('Pada tanggal ${date ?? "21 Agustus 2026"}', style: const TextStyle(fontSize: 9, color: Color(0xFF64748B))),
                        const SizedBox(height: 4),
                        const Text(
                          'Komandan Detasemen Intelijen',
                          textAlign: TextAlign.center,
                          style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 10),
                        QrImageView(
                          data: verifyUrl,
                          version: QrVersions.auto,
                          size: 90,
                          backgroundColor: Colors.white,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          signerName ?? 'KOMANDAN DENINTEL',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, decoration: TextDecoration.underline),
                        ),
                        Text(
                          'Kode TTE: $vCode',
                          style: const TextStyle(fontSize: 8, color: Color(0xFF64748B), fontFamily: 'monospace'),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}