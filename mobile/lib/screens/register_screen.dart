// lib/screens/register_screen.dart
//
// New screen — domiciliataire self-registration, matching the mockup's
// "Créer un compte" step.
//
// Password requirement checklist (min 8 chars, one uppercase, one digit or
// special char) is evaluated live as the user types and rendered as three
// check rows, matching the mock exactly.
//
// Backend contract (AuthController::register):
//   POST /api/auth/register
//   body: { nom, prenom?, email, password, telephone?, role: 'domiciliataire' }
//   - If the account requires admin approval, the response has no token
//     and includes a pending message -> we show a "pending approval" state
//     instead of navigating anywhere (mirrors the Nuxt web behaviour).
//   - If the account is immediately active, response includes {user, token}
//     -> we pop back to LoginScreen so the person can sign in (kept
//     deliberately simple: this screen does not auto-login).
//
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_text_field.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final prenom = TextEditingController();
  final nom = TextEditingController();
  final email = TextEditingController();
  final telephone = TextEditingController();
  final password = TextEditingController();
  final confirm = TextEditingController();

  bool loading = false;
  bool pendingApproval = false;
  String? error;

  @override
  void dispose() {
    prenom.dispose();
    nom.dispose();
    email.dispose();
    telephone.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  bool get hasMinLength => password.text.length >= 8;
  bool get hasUppercase => password.text.contains(RegExp(r'[A-Z]'));
  bool get hasDigitOrSpecial => password.text.contains(RegExp(r'[0-9!@#\$%^&*(),.?":{}|<>]'));

  Future<void> submit() async {
    FocusScope.of(context).unfocus();

    if (nom.text.trim().isEmpty || email.text.trim().isEmpty) {
      setState(() => error = 'Nom et email sont obligatoires.');
      return;
    }
    if (password.text != confirm.text) {
      setState(() => error = 'Les mots de passe ne correspondent pas.');
      return;
    }
    if (!(hasMinLength && hasUppercase && hasDigitOrSpecial)) {
      setState(() => error = 'Le mot de passe ne respecte pas les critères requis.');
      return;
    }

    setState(() {
      loading = true;
      error = null;
    });

    try {
      final result = await widget.api.register(
        nom: nom.text.trim(),
        prenom: prenom.text.trim().isEmpty ? null : prenom.text.trim(),
        email: email.text.trim(),
        password: password.text,
        telephone: telephone.text.trim().isEmpty ? null : telephone.text.trim(),
      );

      if (!mounted) return;

      if (result.pending) {
        setState(() => pendingApproval = true);
      } else {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Compte créé — vous pouvez maintenant vous connecter.')),
        );
      }
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (pendingApproval) return _PendingApprovalView(onDone: () => Navigator.pop(context));

    return Scaffold(
      appBar: AppBar(title: const Text('Créer un compte')),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                const SizedBox(height: 8),
                Center(
                  child: Container(
                    width: 68,
                    height: 68,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.primaryGold, width: 1.4),
                    ),
                    child: const Icon(Icons.person_add_alt_1_outlined, color: AppColors.primaryGold, size: 30),
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Créer un compte',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 26),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Rejoignez la plateforme de domiciliation',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.muted),
                ),
                const SizedBox(height: 30),
                Row(
                  children: [
                    Expanded(child: PremiumTextField(controller: prenom, label: 'Prénom', icon: Icons.person_outline)),
                    const SizedBox(width: 12),
                    Expanded(child: PremiumTextField(controller: nom, label: 'Nom', icon: Icons.person_outline)),
                  ],
                ),
                const SizedBox(height: 12),
                PremiumTextField(
                  controller: email,
                  label: 'Adresse e-mail',
                  icon: Icons.mail_outline,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 12),
                PremiumTextField(
                  controller: telephone,
                  label: 'Téléphone',
                  icon: Icons.call_outlined,
                  keyboardType: TextInputType.phone,
                ),
                const SizedBox(height: 12),
                PremiumTextField(controller: password, label: 'Mot de passe', icon: Icons.lock_outline, obscureText: true),
                const SizedBox(height: 12),
                PremiumTextField(controller: confirm, label: 'Confirmer le mot de passe', icon: Icons.lock_outline, obscureText: true),
                const SizedBox(height: 16),
                _PasswordChecklist(
                  hasMinLength: hasMinLength,
                  hasUppercase: hasUppercase,
                  hasDigitOrSpecial: hasDigitOrSpecial,
                  onChanged: () => setState(() {}),
                  controller: password,
                ),
                if (error != null) ...[
                  const SizedBox(height: 14),
                  ErrorBanner(message: error!),
                ],
                const SizedBox(height: 20),
                PremiumButton(
                  onPressed: submit,
                  loading: loading,
                  icon: Icons.check_circle_outline,
                  label: loading ? 'Création...' : 'Créer mon compte',
                ),
                const SizedBox(height: 20),
                Center(
                  child: TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text.rich(
                      TextSpan(
                        style: TextStyle(color: AppColors.muted),
                        children: [
                          TextSpan(text: 'Déjà un compte ? '),
                          TextSpan(text: 'Se connecter', style: TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w800)),
                        ],
                      ),
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

/// Live password-strength checklist. Listens to the password controller so
/// each row ticks green the moment its rule is satisfied, without requiring
/// the parent to rebuild via onChanged wiring on the TextField itself.
class _PasswordChecklist extends StatefulWidget {
  const _PasswordChecklist({
    required this.controller,
    required this.hasMinLength,
    required this.hasUppercase,
    required this.hasDigitOrSpecial,
    required this.onChanged,
  });

  final TextEditingController controller;
  final bool hasMinLength;
  final bool hasUppercase;
  final bool hasDigitOrSpecial;
  final VoidCallback onChanged;

  @override
  State<_PasswordChecklist> createState() => _PasswordChecklistState();
}

class _PasswordChecklistState extends State<_PasswordChecklist> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(widget.onChanged);
  }

  @override
  void dispose() {
    widget.controller.removeListener(widget.onChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          _ChecklistRow(label: 'Au moins 8 caractères', satisfied: widget.hasMinLength),
          _ChecklistRow(label: 'Une lettre majuscule', satisfied: widget.hasUppercase),
          _ChecklistRow(label: 'Un chiffre ou un caractère spécial', satisfied: widget.hasDigitOrSpecial),
        ],
      ),
    );
  }
}

class _ChecklistRow extends StatelessWidget {
  const _ChecklistRow({required this.label, required this.satisfied});

  final String label;
  final bool satisfied;

  @override
  Widget build(BuildContext context) {
    final color = satisfied ? AppColors.green : AppColors.faint;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(satisfied ? Icons.check_circle : Icons.circle_outlined, size: 17, color: color),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(color: satisfied ? AppColors.text : AppColors.muted, fontSize: 13)),
        ],
      ),
    );
  }
}

/// Shown after a successful registration when the backend requires admin
/// approval before the account can log in (status: 'pending').
class _PendingApprovalView extends StatelessWidget {
  const _PendingApprovalView({required this.onDone});

  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.amber.withOpacity(0.12),
                    ),
                    child: const Icon(Icons.hourglass_top_rounded, color: AppColors.amber, size: 40),
                  ),
                  const SizedBox(height: 22),
                  Text('Demande envoyée', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 10),
                  const Text(
                    'Votre compte est en attente de validation par un administrateur. '
                    'Vous recevrez une confirmation dès que votre compte sera activé.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.muted, height: 1.5),
                  ),
                  const SizedBox(height: 24),
                  PremiumButton(label: 'Retour à la connexion', onPressed: onDone),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
