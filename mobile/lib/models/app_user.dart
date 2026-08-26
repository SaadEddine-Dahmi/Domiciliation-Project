class AppUser {
  AppUser({
    required this.id,
    required this.email,
    required this.role,
    required this.status,
    required this.name,
    this.mustChangePassword = false,
  });

  final int id;
  final String email;
  final String role;
  final String status;
  final String name;
  final bool mustChangePassword;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    final nom = (json['nom'] ?? '').toString();
    final prenom = (json['prenom'] ?? '').toString();
    final fullName = '$nom $prenom'.trim();

    return AppUser(
      id: int.tryParse('${json['id']}') ?? 0,
      email: (json['email'] ?? '').toString(),
      role: (json['role'] ?? 'client').toString(),
      status: (json['status'] ?? 'active').toString(),
      name: fullName.isEmpty ? (json['email'] ?? 'Utilisateur').toString() : fullName,
      mustChangePassword: json['must_change_password'] == true,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'email': email,
        'role': role,
        'status': status,
        'nom': name,
        'must_change_password': mustChangePassword,
      };

  bool get isAdmin => role == 'admin';
  bool get isDomiciliataire => role == 'domiciliataire';
  bool get isClient => role == 'client';

  String get roleLabel {
    switch (role) {
      case 'admin':
        return 'Super Admin';
      case 'domiciliataire':
        return 'Domiciliataire';
      default:
        return 'Client';
    }
  }
}
