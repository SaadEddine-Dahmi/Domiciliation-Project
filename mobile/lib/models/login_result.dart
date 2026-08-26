import 'app_user.dart';

class LoginResult {
  LoginResult({required this.user, required this.token});

  final AppUser user;
  final String token;
}
