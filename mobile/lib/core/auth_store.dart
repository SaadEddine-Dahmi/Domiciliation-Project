import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/app_user.dart';

class AuthStore {
  static const key = 'mobile_auth';

  static Future<({AppUser? user, String? token})> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(key);
    if (raw == null) return (user: null, token: null);

    final data = jsonDecode(raw) as Map<String, dynamic>;
    return (
      user: AppUser.fromJson(data['user'] as Map<String, dynamic>),
      token: data['token'] as String?,
    );
  }

  static Future<void> save(AppUser user, String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      key,
      jsonEncode({'user': user.toJson(), 'token': token}),
    );
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }
}
