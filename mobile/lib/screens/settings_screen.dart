import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/theme_controller.dart';
import '../models/app_user.dart';
import '../widgets/error_banner.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({
    super.key,
    required this.api,
    required this.user,
    required this.themeController,
  });

  final ApiClient api;
  final AppUser user;
  final ThemeController themeController;

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final nom = TextEditingController();
  final prenom = TextEditingController();
  final telephone = TextEditingController();
  final societe = TextEditingController();
  final contractTitle = TextEditingController();
  final rc = TextEditingController();
  final ifFiscal = TextEditingController();
  final tp = TextEditingController();
  final currentPassword = TextEditingController();
  final newPassword = TextEditingController();
  final confirmPassword = TextEditingController();

  Map<String, dynamic>? profile;
  List<dynamic> history = [];
  bool loading = true;
  bool savingProfile = false;
  bool changingPassword = false;
  bool exporting = false;
  bool loadingHistory = false;
  String? historyError;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final loadedProfile = await widget.api.profile().timeout(const Duration(seconds: 12));
      if (!mounted) return;
      setState(() {
        profile = loadedProfile;
        loading = false;
        error = null;
      });
      nom.text = '${loadedProfile['nom'] ?? ''}';
      prenom.text = '${loadedProfile['prenom'] ?? ''}';
      telephone.text = '${loadedProfile['telephone'] ?? ''}';
      societe.text = '${loadedProfile['nom_societe'] ?? ''}';
      contractTitle.text = '${loadedProfile['contract_title'] ?? ''}';
      rc.text = '${loadedProfile['rc'] ?? ''}';
      ifFiscal.text = '${loadedProfile['if_fiscal'] ?? ''}';
      tp.text = '${loadedProfile['tp'] ?? ''}';
      loadHistory();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loading = false;
      });
    }
  }

  Future<void> loadHistory() async {
    if (widget.user.isAdmin) return;
    setState(() {
      loadingHistory = true;
      historyError = null;
    });
    try {
      final loadedHistory = await widget.api.accountHistory(limit: 8).timeout(const Duration(seconds: 10));
      if (!mounted) return;
      setState(() => history = loadedHistory);
    } catch (e) {
      if (!mounted) return;
      setState(() => historyError = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => loadingHistory = false);
    }
  }

  @override
  void dispose() {
    for (final controller in [
      nom,
      prenom,
      telephone,
      societe,
      contractTitle,
      rc,
      ifFiscal,
      tp,
      currentPassword,
      newPassword,
      confirmPassword,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> saveProfile() async {
    setState(() => savingProfile = true);
    try {
      final payload = widget.user.isAdmin
          ? {
              'nom': nom.text.trim(),
              'prenom': prenom.text.trim(),
              'telephone': telephone.text.trim(),
            }
          : {
              'nom': nom.text.trim(),
              'prenom': prenom.text.trim(),
              'telephone': telephone.text.trim(),
              'nom_societe': societe.text.trim(),
              'contract_title': contractTitle.text.trim(),
              'rc': rc.text.trim(),
              'if_fiscal': ifFiscal.text.trim(),
              'tp': tp.text.trim(),
            };
      await widget.api.updateProfile(payload);
      await load();
      if (mounted) showSnack('Profil mis a jour');
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => savingProfile = false);
    }
  }

  Future<void> pickPhoto() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
      withData: true,
    );
    final file = result?.files.single;
    if (file == null) return;
    try {
      final updated = await widget.api.uploadProfilePhoto(file);
      setState(() {
        profile = {...?profile, ...updated, '_photo_version': DateTime.now().millisecondsSinceEpoch};
      });
      showSnack('Photo mise a jour');
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    }
  }

  Future<void> removePhoto() async {
    try {
      await widget.api.deleteProfilePhoto();
      await load();
      showSnack('Photo supprimee');
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    }
  }

  Future<void> updatePassword() async {
    if (newPassword.text.length < 8) {
      setState(() => error = 'Le nouveau mot de passe doit contenir au moins 8 caracteres.');
      return;
    }
    if (newPassword.text != confirmPassword.text) {
      setState(() => error = 'La confirmation ne correspond pas.');
      return;
    }

    setState(() => changingPassword = true);
    try {
      await widget.api.changePassword(
        currentPassword: currentPassword.text,
        password: newPassword.text,
        confirmation: confirmPassword.text,
      );
      currentPassword.clear();
      newPassword.clear();
      confirmPassword.clear();
      showSnack('Mot de passe mis a jour');
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => changingPassword = false);
    }
  }

  Future<void> exportArchive(String format) async {
    setState(() => exporting = true);
    try {
      final content = await widget.api.accountHistoryExport(format);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: Text('Export ${format.toUpperCase()}'),
          content: SizedBox(
            width: double.maxFinite,
            child: SingleChildScrollView(child: SelectableText(content)),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer')),
          ],
        ),
      );
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => exporting = false);
    }
  }

  void showSnack(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final photoUrl = profile?['photo_url']?.toString();
    final photoVersion = profile?['_photo_version'];
    final effectivePhotoUrl = photoUrl == null || photoUrl.isEmpty
        ? null
        : '$photoUrl${photoUrl.contains('?') ? '&' : '?'}_pv=${photoVersion ?? 0}';

    return Scaffold(
      appBar: AppBar(title: const Text('Parametres')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
          if (error != null) ErrorBanner(message: error!),
          const SizedBox(height: 12),
          SettingsCard(
            title: 'Profil',
            icon: Icons.person_outline,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 34,
                    backgroundImage: effectivePhotoUrl == null ? null : NetworkImage(effectivePhotoUrl),
                    child: effectivePhotoUrl == null ? Text('${profile?['initials'] ?? '?'}') : null,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        FilledButton.tonalIcon(onPressed: pickPhoto, icon: const Icon(Icons.photo_camera_outlined), label: const Text('Changer')),
                        if (photoUrl != null) OutlinedButton.icon(onPressed: removePhoto, icon: const Icon(Icons.delete_outline), label: const Text('Supprimer')),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              ResponsiveFieldPair(
                first: SettingsField(controller: nom, label: 'Nom'),
                second: SettingsField(controller: prenom, label: 'Prenom'),
              ),
              SettingsField(controller: telephone, label: 'Telephone'),
              if (!widget.user.isAdmin) ...[
                SettingsField(controller: societe, label: 'Societe'),
                SettingsField(controller: contractTitle, label: 'Titre contrat par defaut'),
                ResponsiveFieldTriple(
                  first: SettingsField(controller: rc, label: 'RC'),
                  second: SettingsField(controller: ifFiscal, label: 'IF'),
                  third: SettingsField(controller: tp, label: 'TP'),
                ),
              ],
              FilledButton.icon(
                onPressed: savingProfile ? null : saveProfile,
                icon: const Icon(Icons.save_outlined),
                label: Text(savingProfile ? 'Enregistrement...' : 'Enregistrer'),
              ),
            ],
          ),
          SettingsCard(
            title: 'Apparence',
            icon: Icons.contrast_outlined,
            children: [
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: SegmentedButton<ThemeMode>(
                  segments: const [
                    ButtonSegment(value: ThemeMode.system, label: Text('Systeme'), icon: Icon(Icons.phone_android)),
                    ButtonSegment(value: ThemeMode.light, label: Text('Clair'), icon: Icon(Icons.light_mode)),
                    ButtonSegment(value: ThemeMode.dark, label: Text('Sombre'), icon: Icon(Icons.dark_mode)),
                  ],
                  selected: {widget.themeController.mode},
                  onSelectionChanged: (value) => widget.themeController.setMode(value.first),
                ),
              ),
            ],
          ),
          SettingsCard(
            title: 'Securite',
            icon: Icons.lock_outline,
            children: [
              SettingsField(controller: currentPassword, label: 'Mot de passe actuel', obscure: true),
              SettingsField(controller: newPassword, label: 'Nouveau mot de passe', obscure: true),
              SettingsField(controller: confirmPassword, label: 'Confirmation', obscure: true),
              FilledButton.icon(
                onPressed: changingPassword ? null : updatePassword,
                icon: const Icon(Icons.key_outlined),
                label: Text(changingPassword ? 'Mise a jour...' : 'Changer le mot de passe'),
              ),
            ],
          ),
          if (!widget.user.isAdmin)
            SettingsCard(
              title: 'Historique & export',
              icon: Icons.history_outlined,
              children: [
                if (loadingHistory)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: LinearProgressIndicator(),
                  ),
                if (historyError != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: ErrorBanner(message: historyError!),
                  ),
                for (final item in history)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.timeline_outlined),
                    title: Text('${item['label'] ?? item['type'] ?? 'Modification'}'),
                    subtitle: Text('${item['action'] ?? ''} - ${item['created_at'] ?? ''}'),
                  ),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    OutlinedButton.icon(
                      onPressed: exporting ? null : () => exportArchive('json'),
                      icon: const Icon(Icons.data_object_outlined),
                      label: const Text('JSON'),
                    ),
                    OutlinedButton.icon(
                      onPressed: exporting ? null : () => exportArchive('html'),
                      icon: const Icon(Icons.code_outlined),
                      label: const Text('HTML'),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class SettingsCard extends StatelessWidget {
  const SettingsCard({super.key, required this.title, required this.icon, required this.children});

  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(icon, color: Theme.of(context).colorScheme.primary),
                const SizedBox(width: 8),
                Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
              ],
            ),
            const SizedBox(height: 16),
            ...children,
          ],
        ),
      ),
    );
  }
}

class ResponsiveFieldPair extends StatelessWidget {
  const ResponsiveFieldPair({super.key, required this.first, required this.second});

  final Widget first;
  final Widget second;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 430) {
          return Column(children: [first, second]);
        }
        return Row(
          children: [
            Expanded(child: first),
            const SizedBox(width: 12),
            Expanded(child: second),
          ],
        );
      },
    );
  }
}

class ResponsiveFieldTriple extends StatelessWidget {
  const ResponsiveFieldTriple({super.key, required this.first, required this.second, required this.third});

  final Widget first;
  final Widget second;
  final Widget third;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 520) {
          return Column(children: [first, second, third]);
        }
        return Row(
          children: [
            Expanded(child: first),
            const SizedBox(width: 8),
            Expanded(child: second),
            const SizedBox(width: 8),
            Expanded(child: third),
          ],
        );
      },
    );
  }
}

class SettingsField extends StatelessWidget {
  const SettingsField({super.key, required this.controller, required this.label, this.obscure = false});

  final TextEditingController controller;
  final String label;
  final bool obscure;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: controller,
        obscureText: obscure,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }
}
