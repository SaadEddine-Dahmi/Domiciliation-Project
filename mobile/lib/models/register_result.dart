import 'app_user.dart';

class RegisterResult {
  RegisterResult({required this.pending, this.user, this.token});

  final bool pending;
  final AppUser? user;
  final String? token;
}
