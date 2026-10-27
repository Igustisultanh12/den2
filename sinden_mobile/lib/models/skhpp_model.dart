class SkhppModel {
  final int id;
  final String? noSkhpp;
  final String nama;
  final String? pangkatKorpsNrp;
  final String? jabatan;
  final String? kesatuan;
  final String? peruntukan;
  final String kategori; // militer, sipil, nikah
  final String status;   // draft, pending, approved, rejected
  final String? barcodeString;
  final String? signedAt;
  final String? tanggalSurat;
  final String? createdBy;

  SkhppModel({
    required this.id,
    this.noSkhpp,
    required this.nama,
    this.pangkatKorpsNrp,
    this.jabatan,
    this.kesatuan,
    this.peruntukan,
    required this.kategori,
    required this.status,
    this.barcodeString,
    this.signedAt,
    this.tanggalSurat,
    this.createdBy,
  });

  bool get isApproved => status.toLowerCase() == 'approved';
  bool get isRejected => status.toLowerCase() == 'rejected';
  bool get isPending => status.toLowerCase() == 'pending';

  factory SkhppModel.fromJson(Map<String, dynamic> json) {
    return SkhppModel(
      id: json['id'] ?? 0,
      noSkhpp: json['no_skhpp'] ?? json['nomor_skhpp'],
      nama: json['nama'] ?? '',
      pangkatKorpsNrp: json['pangkat_korps_nrp'],
      jabatan: json['jabatan'] ?? json['jabatan_pekerjaan'],
      kesatuan: json['kesatuan'] ?? 'Denintel Koarmada 2',
      peruntukan: json['peruntukan'],
      kategori: json['kategori'] ?? json['kategori_personel'] ?? 'militer',
      status: json['status'] ?? 'pending',
      barcodeString: json['barcode_string'] ?? json['verification_code'],
      signedAt: json['signed_at'] ?? json['approved_at'],
      tanggalSurat: json['tanggal_surat'] ?? json['tanggal_skhpp'] ?? json['created_at'],
      createdBy: json['creator'] != null ? json['creator']['name'] : (json['operator_name'] ?? 'Operator'),
    );
  }
}