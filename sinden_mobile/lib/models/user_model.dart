class UserModel {
  final int id;
  final String name;
  final String? email;
  final String? pangkat;
  final String? korps;
  final String? nrp;
  final String? role;
  final String? jabatan;
  final String? phone;
  final String? avatar;
  final double? latitude;
  final double? longitude;
  final String? lastActive;

  // Authorization Flags
  final bool canAccessAgenda;
  final bool canAccessLetters;
  final bool canAccessSkhpp;
  final bool canAccessCategories;
  final bool canAccessSignature;
  final bool canAccessCash;
  final bool canAccessCommander;
  final bool canAccessMitra;
  final bool canAccessTechnicalCash;
  final bool canAccessViolations;
  final bool canAccessActivities;
  final bool canAccessUsers;
  final bool canAccessSettings;

  UserModel({
    required this.id,
    required this.name,
    this.email,
    this.pangkat,
    this.korps,
    this.nrp,
    this.role,
    this.jabatan,
    this.phone,
    this.avatar,
    this.latitude,
    this.longitude,
    this.lastActive,
    this.canAccessAgenda = true,
    this.canAccessLetters = true,
    this.canAccessSkhpp = true,
    this.canAccessCategories = true,
    this.canAccessSignature = true,
    this.canAccessCash = false,
    this.canAccessCommander = false,
    this.canAccessMitra = false,
    this.canAccessTechnicalCash = false,
    this.canAccessViolations = true,
    this.canAccessActivities = true,
    this.canAccessUsers = false,
    this.canAccessSettings = false,
  });

  bool get isSuperAdmin => role == 'super_admin';
  bool get isAdmin => role == 'admin' || role == 'super_admin';
  bool get isPersonel => role == 'personel' || role == 'user';
  bool get isPersonelSintel => role == 'personel_sintel' || role == 'anggotasintel';
  bool get isKomandan => role == 'komandan' || role == 'dan_unit_teknis' || (jabatan != null && jabatan!.toLowerCase().contains('komandan'));

  String get fullIdentity {
    final parts = <String>[];
    if (pangkat != null && pangkat!.trim().isNotEmpty) parts.add(pangkat!.trim());
    if (korps != null && korps!.trim().isNotEmpty) parts.add(korps!.trim());
    if (name.trim().isNotEmpty) parts.add(name.trim());
    if (nrp != null && nrp!.trim().isNotEmpty) parts.add("NRP " + nrp!.trim());
    return parts.join(' ');
  }

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final r = json['role'] ?? 'personel';
    final isSuper = r == 'super_admin';
    final isAdm = r == 'admin' || isSuper;

    return UserModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      email: json['email'],
      pangkat: json['pangkat'],
      korps: json['korps'],
      nrp: json['nrp'] ?? json['username'],
      role: r,
      jabatan: json['jabatan'],
      phone: json['phone'] ?? json['no_wa'],
      avatar: json['avatar'],
      latitude: json['latitude'] != null ? double.tryParse(json['latitude'].toString()) : null,
      longitude: json['longitude'] != null ? double.tryParse(json['longitude'].toString()) : null,
      lastActive: json['last_seen_at'] ?? json['updated_at'],
      canAccessAgenda: isAdm || (json['can_access_agenda'] ?? true),
      canAccessLetters: isAdm || (json['can_access_letters'] ?? true),
      canAccessSkhpp: isAdm || (json['can_access_skhpp'] ?? true),
      canAccessCategories: isAdm || (json['can_access_categories'] ?? true),
      canAccessSignature: isAdm || (json['can_access_signature'] ?? true),
      canAccessCash: false,
      canAccessCommander: isAdm || (json['can_access_commander'] ?? false),
      canAccessMitra: isAdm || (json['can_access_mitra'] ?? false),
      canAccessTechnicalCash: false,
      canAccessViolations: isAdm || (json['can_access_violations'] ?? true),
      canAccessActivities: isAdm || (json['can_access_activities'] ?? true),
      canAccessUsers: isAdm || (json['can_access_users'] ?? false),
      canAccessSettings: isSuper || (json['can_access_settings'] ?? false),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'pangkat': pangkat,
      'korps': korps,
      'nrp': nrp,
      'role': role,
      'jabatan': jabatan,
      'phone': phone,
      'avatar': avatar,
      'latitude': latitude,
      'longitude': longitude,
      'last_seen_at': lastActive,
      'can_access_agenda': canAccessAgenda,
      'can_access_letters': canAccessLetters,
      'can_access_skhpp': canAccessSkhpp,
      'can_access_categories': canAccessCategories,
      'can_access_signature': canAccessSignature,
      'can_access_cash': canAccessCash,
      'can_access_commander': canAccessCommander,
      'can_access_mitra': canAccessMitra,
      'can_access_technical_cash': canAccessTechnicalCash,
      'can_access_violations': canAccessViolations,
      'can_access_activities': canAccessActivities,
      'can_access_users': canAccessUsers,
      'can_access_settings': canAccessSettings,
    };
  }
}