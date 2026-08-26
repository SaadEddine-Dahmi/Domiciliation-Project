import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../models/app_user.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_text_field.dart';

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
                const SizedBox(height: 34),
                Center(
                  child: Container(
                    width: 82,
                    height: 82,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(26),
                      border: Border.all(color: AppColors.primaryGold),
                    ),
                    child: const Icon(Icons.account_balance_outlined, size: 42, color: AppColors.primaryGold),
                  ),
                ),
                const SizedBox(height: 20),
                Text('Bienvenue', textAlign: TextAlign.center, style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 42)),
                const SizedBox(height: 8),
                const Text('Connectez-vous a votre espace', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 42),
                PremiumTextField(controller: email, label: 'Adresse e-mail', icon: Icons.mail_outline, keyboardType: TextInputType.emailAddress),
                const SizedBox(height: 14),
                PremiumTextField(controller: password, label: 'Mot de passe', icon: Icons.lock_outline, obscureText: true, onSubmitted: (_) => submit()),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(onPressed: () {}, child: const Text('Mot de passe oublie ?')),
                ),
                if (error != null) ...[
                  const SizedBox(height: 8),
                  ErrorBanner(message: error!),
                ],
                const SizedBox(height: 18),
                PremiumButton(onPressed: submit, loading: loading, icon: Icons.login_rounded, label: loading ? 'Connexion...' : 'Se connecter'),
                const SizedBox(height: 32),
                Row(
                  children: const [
                    Expanded(child: Divider()),
                    Padding(padding: EdgeInsets.symmetric(horizontal: 12), child: Text('Pas encore de compte ?', style: TextStyle(color: AppColors.muted))),
                    Expanded(child: Divider()),
                  ],
                ),
                const SizedBox(height: 16),
                PremiumButton(
                  label: 'Creer un compte',
                  outlined: true,
                  icon: Icons.person_add_alt_1_outlined,
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Creation de compte via l administration pour le moment.')),
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
