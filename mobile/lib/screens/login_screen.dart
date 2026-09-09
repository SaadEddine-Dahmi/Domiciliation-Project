// lib/screens/login_screen.dart
//
// Login screen — full redesign to match the approved dark/gold mockup.
//
// Layout:
//   - Wordmark ("D" monogram in a bordered gold square + app name) instead
//     of a generic icon, matching the branded splash treatment in the mock.
//   - Email / password fields using the shared PremiumTextField.
//   - "Mot de passe oublié ?" link (no handler wired yet — placeholder).
//   - Primary "Se connecter" button + secondary "Créer un compte" link
//     that pushes RegisterScreen.
//
// Auth flow is unchanged from the previous version: api.login() returns
// {user, token}; onLoggedIn() is provided by the app shell to persist the
// session and route to the correct dashboard.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/app_config.dart';
import '../models/app_user.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_text_field.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.onLoggedIn});

  final ApiClient api;
  final Future<void> Function(AppUser user, String token) onLoggedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool loading = false;
  String? error;

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    FocusScope.of(context).unfocus();
    setState(() {
      loading = true;
      error = null;
    });

    try {
      final result = await widget.api.login(email.text.trim(), password.text);
      await widget.onLoggedIn(result.user, result.token);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void goToRegister() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => RegisterScreen(api: widget.api)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                const SizedBox(height: 40),
                const _Wordmark(),
                const SizedBox(height: 40),
                Text(
                  'Bienvenue',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 34),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Connectez-vous à votre espace',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.muted),
                ),
                const SizedBox(height: 36),
                PremiumTextField(
                  controller: email,
                  label: 'Adresse e-mail',
                  icon: Icons.mail_outline,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 14),
                PremiumTextField(
                  controller: password,
                  label: 'Mot de passe',
                  icon: Icons.lock_outline,
                  obscureText: true,
                  onSubmitted: (_) => submit(),
                ),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () {},
                    child: const Text('Mot de passe oublié ?'),
                  ),
                ),
                if (error != null) ...[
                  const SizedBox(height: 4),
                  ErrorBanner(message: error!),
                ],
                const SizedBox(height: 14),
                PremiumButton(
                  onPressed: submit,
                  loading: loading,
                  icon: Icons.login_rounded,
                  label: loading ? 'Connexion...' : 'Se connecter',
                ),
                const SizedBox(height: 28),
                Row(
                  children: const [
                    Expanded(child: Divider(color: AppColors.border)),
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 12),
                      child: Text('Pas encore de compte ?', style: TextStyle(color: AppColors.muted)),
                    ),
                    Expanded(child: Divider(color: AppColors.border)),
                  ],
                ),
                const SizedBox(height: 16),
                PremiumButton(
                  label: 'Créer un compte',
                  outlined: true,
                  icon: Icons.person_add_alt_1_outlined,
                  onPressed: goToRegister,
                ),
                const SizedBox(height: 20),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text.rich(
                    TextSpan(
                      style: const TextStyle(color: AppColors.faint, fontSize: 12, height: 1.5),
                      children: [
                        const TextSpan(text: 'En vous connectant, vous acceptez nos '),
                        TextSpan(
                          text: "Conditions d'utilisation",
                          style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w700),
                          recognizer: null,
                        ),
                        const TextSpan(text: ' et notre '),
                        TextSpan(
                          text: 'Politique de confidentialité.',
                          style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                    textAlign: TextAlign.center,
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

/// Branded monogram + app name, reused identically on the register screen's
/// back-navigation context. Reads the name from AppConfig so a future
/// rename only touches one constant.
class _Wordmark extends StatelessWidget {
  const _Wordmark();

  @override
  Widget build(BuildContext context) {
    final name = AppConfig.appName.toUpperCase();
    return Column(
      children: [
        Container(
          width: 72,
          height: 72,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.primaryGold, width: 1.4),
          ),
          child: Text(
            name.isEmpty ? '?' : name.substring(0, 1),
            style: const TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 34,
              fontWeight: FontWeight.w700,
              color: AppColors.primaryGold,
            ),
          ),
        ),
        const SizedBox(height: 14),
        Text(
          name,
          style: const TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 15,
            letterSpacing: 3,
            color: AppColors.text,
          ),
        ),
      ],
    );
  }
}