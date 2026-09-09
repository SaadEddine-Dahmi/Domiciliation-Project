// lib/screens/profile_screen.dart
//
// Profile — redesigned to match the mockup for the domiciliataire role:
//   - Completion ring card ("Profil complété à X%") with a shortcut link.
//   - "IDENTITÉ ENTREPRISE" section (edit pencil -> inline edit sheet).
//   - "CONTACT" section (edit pencil -> inline edit sheet).
//   - "ADRESSES" section: reorderable list with per-row edit/delete, and
//     an "+ Ajouter" action, mirroring AddressListEditor.vue on web.
//
// Data source: GET/PUT /api/profile (DomiciliaireProfileController).
// adresses is a JSON array of {label, value} objects; index 0 is treated
// as the siège social by convention on the backend, but this screen does
// not enforce that — it just preserves whatever order the user sets.
//
// Completion percentage logic mirrors User::hasCompleteProfile() on the
// backend (nom_societe, representant_legal, at least one address) plus
// RC/IF for a finer-grained ring, matching the mockup's "80%" example.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_card.dart';
import '../widgets/premium_text_field.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic> data = {};
  List<Map<String, String>> addresses = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await widget.api.profile();
      if (!mounted) return;
      setState(() {
        data = Map<String, dynamic>.from(res as Map);
        addresses = _normalise(data['adresses'] ?? data['addresses']);
        loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loading = false;
      });
    }
  }

  List<Map<String, String>> _normalise(dynamic raw) {
    if (raw is! List) return [];
    return raw
        .map((item) => item is Map
            ? {
                'label': '${item['label'] ?? ''}',
                'value': '${item['value'] ?? ''}'
              }
            : {'label': '', 'value': '$item'})
        .where((a) => a['value']!.trim().isNotEmpty)
        .toList()
        .cast<Map<String, String>>();
  }

  int get completionPercent {
    final checks = [
      '${data['nom_societe'] ?? ''}'.trim().isNotEmpty,
      '${data['representant_legal'] ?? ''}'.trim().isNotEmpty,
      '${data['identite_representant'] ?? ''}'.trim().isNotEmpty,
      '${data['rc'] ?? ''}'.trim().isNotEmpty,
      '${data['if_fiscal'] ?? ''}'.trim().isNotEmpty,
      addresses.isNotEmpty,
    ];
    return ((checks.where((c) => c).length / checks.length) * 100).round();
  }

  Future<void> saveField(Map<String, dynamic> partial) async {
    try {
      await widget.api.updateProfile(partial);
      await load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e is ApiException ? e.message : e.toString())),
      );
    }
  }

  Future<void> saveAddresses(List<Map<String, String>> updated) async {
    final valid = updated
        .where((a) =>
            a['label']!.trim().isNotEmpty && a['value']!.trim().isNotEmpty)
        .toList();
    try {
      await widget.api.updateProfile({'adresses': valid});
      setState(() => addresses = valid);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e is ApiException ? e.message : e.toString())),
      );
    }
  }

  Future<void> editIdentitySheet() async {
    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditIdentitySheet(data: data),
    );
    if (result != null) await saveField(result);
  }

  Future<void> editContactSheet() async {
    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditContactSheet(data: data),
    );
    if (result != null) await saveField(result);
  }

  Future<void> addOrEditAddress({int? index}) async {
    final existing = index == null ? null : addresses[index];
    final result = await showModalBottomSheet<Map<String, String>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditAddressSheet(existing: existing),
    );
    if (result == null) return;

    final updated = [...addresses];
    if (index == null) {
      updated.add(result);
    } else {
      updated[index] = result;
    }
    await saveAddresses(updated);
  }

  Future<void> removeAddress(int index) async {
    final updated = [...addresses]..removeAt(index);
    await saveAddresses(updated);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.page),
                children: [
                  if (error != null) ...[
                    ErrorBanner(message: error!),
                    const SizedBox(height: 14)
                  ],
                  PremiumCard(
                    child: Row(
                      children: [
                        SizedBox(
                          width: 76,
                          height: 76,
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              CircularProgressIndicator(
                                value: completionPercent / 100,
                                strokeWidth: 7,
                                color: AppColors.primaryGold,
                                backgroundColor: AppColors.surfaceRaised,
                              ),
                              Text('$completionPercent%',
                                  style: const TextStyle(
                                      fontFamily: 'Fraunces',
                                      fontSize: 19,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.primaryGold)),
                            ],
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Profil complété à $completionPercent%',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w900,
                                      fontSize: 14.5)),
                              const SizedBox(height: 4),
                              const Text(
                                'Complétez votre profil pour accéder à toutes les fonctionnalités.',
                                style: TextStyle(
                                    color: AppColors.muted, fontSize: 12),
                              ),
                              if (completionPercent < 100) ...[
                                const SizedBox(height: 6),
                                GestureDetector(
                                  onTap: editIdentitySheet,
                                  child: const Text('Compléter maintenant →',
                                      style: TextStyle(
                                          color: AppColors.primaryGold,
                                          fontWeight: FontWeight.w800,
                                          fontSize: 12.5)),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  _Section(
                    title: 'IDENTITÉ ENTREPRISE',
                    onEdit: editIdentitySheet,
                    rows: [
                      _row('Raison sociale', data['nom_societe']),
                      _row(
                          'Forme juridique', data['forme_juridique'] ?? 'SARL'),
                      _row('Capital social', data['capital_social']),
                      _row('ICE', data['ice'] ?? data['if_fiscal']),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _Section(
                    title: 'CONTACT',
                    onEdit: editContactSheet,
                    rows: [
                      _row(
                          'Nom complet',
                          '${data['prenom'] ?? ''} ${data['nom'] ?? ''}'
                              .trim()),
                      _row('Email', data['email']),
                      _row('Téléphone', data['telephone']),
                    ],
                  ),
                  const SizedBox(height: 14),
                  PremiumCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Expanded(
                              child: Text('ADRESSES',
                                  style: TextStyle(
                                      color: AppColors.primaryGold,
                                      fontWeight: FontWeight.w900,
                                      fontSize: 12,
                                      letterSpacing: 1)),
                            ),
                            TextButton.icon(
                              onPressed: () => addOrEditAddress(),
                              icon: const Icon(Icons.add, size: 16),
                              label: const Text('Ajouter'),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        if (addresses.isEmpty)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 10),
                            child: Text('Aucune adresse enregistrée.',
                                style: TextStyle(color: AppColors.muted)),
                          )
                        else
                          ReorderableListView.builder(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: addresses.length,
                            onReorder: (oldIndex, newIndex) {
                              if (newIndex > oldIndex) newIndex -= 1;
                              final updated = [...addresses];
                              final moved = updated.removeAt(oldIndex);
                              updated.insert(newIndex, moved);
                              saveAddresses(updated);
                            },
                            itemBuilder: (context, index) {
                              final address = addresses[index];
                              return Padding(
                                key: ValueKey(
                                    '${address['label']}-${address['value']}-$index'),
                                padding: const EdgeInsets.only(bottom: 8),
                                child: Row(
                                  children: [
                                    const Icon(Icons.drag_indicator,
                                        color: AppColors.faint, size: 18),
                                    const SizedBox(width: 6),
                                    const Icon(Icons.location_on_outlined,
                                        size: 17, color: AppColors.primaryGold),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(address['label'] ?? '',
                                              style: const TextStyle(
                                                  fontWeight: FontWeight.w800,
                                                  fontSize: 13)),
                                          Text(address['value'] ?? '',
                                              style: const TextStyle(
                                                  color: AppColors.muted,
                                                  fontSize: 12)),
                                        ],
                                      ),
                                    ),
                                    IconButton(
                                      onPressed: () =>
                                          addOrEditAddress(index: index),
                                      icon: const Icon(Icons.edit_outlined,
                                          size: 17,
                                          color: AppColors.primaryGold),
                                    ),
                                    IconButton(
                                      onPressed: () => removeAddress(index),
                                      icon: const Icon(Icons.delete_outline,
                                          size: 17, color: AppColors.red),
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 40),
                ],
              ),
            ),
    );
  }

  _InfoRow _row(String label, dynamic value) =>
      _InfoRow(label, '${value ?? ''}'.trim().isEmpty ? '-' : '$value');
}

class _Section extends StatelessWidget {
  const _Section(
      {required this.title, required this.rows, required this.onEdit});

  final String title;
  final List<_InfoRow> rows;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(title,
                    style: const TextStyle(
                        color: AppColors.primaryGold,
                        fontWeight: FontWeight.w900,
                        fontSize: 12,
                        letterSpacing: 1)),
              ),
              IconButton(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined,
                      size: 17, color: AppColors.primaryGold)),
            ],
          ),
          for (final row in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                      child: Text(row.label,
                          style: const TextStyle(
                              color: AppColors.muted, fontSize: 13))),
                  Expanded(
                    child: Text(row.value,
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 13)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _InfoRow {
  const _InfoRow(this.label, this.value);
  final String label;
  final String value;
}

/// Edit sheet for the "IDENTITÉ ENTREPRISE" section. Returns a partial
/// payload compatible with PUT /api/profile on save, or null on cancel.
class _EditIdentitySheet extends StatefulWidget {
  const _EditIdentitySheet({required this.data});

  final Map<String, dynamic> data;

  @override
  State<_EditIdentitySheet> createState() => _EditIdentitySheetState();
}

class _EditIdentitySheetState extends State<_EditIdentitySheet> {
  late final nomSociete =
      TextEditingController(text: '${widget.data['nom_societe'] ?? ''}');
  late final representant =
      TextEditingController(text: '${widget.data['representant_legal'] ?? ''}');
  late final identite = TextEditingController(
      text: '${widget.data['identite_representant'] ?? ''}');
  late final rc = TextEditingController(text: '${widget.data['rc'] ?? ''}');
  late final ifFiscal =
      TextEditingController(text: '${widget.data['if_fiscal'] ?? ''}');
  late final tp = TextEditingController(text: '${widget.data['tp'] ?? ''}');

  @override
  void dispose() {
    for (final c in [nomSociete, representant, identite, rc, ifFiscal, tp]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return _SheetShell(
      title: 'Identité entreprise',
      onSave: () => Navigator.pop(context, {
        'nom_societe': nomSociete.text.trim(),
        'representant_legal': representant.text.trim(),
        'identite_representant': identite.text.trim(),
        'rc': rc.text.trim(),
        'if_fiscal': ifFiscal.text.trim(),
        'tp': tp.text.trim(),
      }),
      children: [
        PremiumTextField(
            controller: nomSociete,
            label: 'Raison sociale',
            icon: Icons.business_outlined),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: representant,
            label: 'Représentant légal',
            icon: Icons.person_outline),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: identite,
            label: 'CIN / Passeport représentant',
            icon: Icons.badge_outlined),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: rc, label: 'RC', icon: Icons.numbers_outlined),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: ifFiscal,
            label: 'Identifiant fiscal (IF)',
            icon: Icons.numbers_outlined),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: tp,
            label: 'Taxe professionnelle (TP)',
            icon: Icons.numbers_outlined),
      ],
    );
  }
}

/// Edit sheet for the "CONTACT" section.
class _EditContactSheet extends StatefulWidget {
  const _EditContactSheet({required this.data});

  final Map<String, dynamic> data;

  @override
  State<_EditContactSheet> createState() => _EditContactSheetState();
}

class _EditContactSheetState extends State<_EditContactSheet> {
  late final nom = TextEditingController(text: '${widget.data['nom'] ?? ''}');
  late final prenom =
      TextEditingController(text: '${widget.data['prenom'] ?? ''}');
  late final telephone =
      TextEditingController(text: '${widget.data['telephone'] ?? ''}');

  @override
  void dispose() {
    nom.dispose();
    prenom.dispose();
    telephone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return _SheetShell(
      title: 'Contact',
      onSave: () => Navigator.pop(context, {
        'nom': nom.text.trim(),
        'prenom': prenom.text.trim(),
        'telephone': telephone.text.trim(),
      }),
      children: [
        PremiumTextField(
            controller: nom, label: 'Nom', icon: Icons.person_outline),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: prenom, label: 'Prénom', icon: Icons.person_outline),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: telephone,
            label: 'Téléphone',
            icon: Icons.call_outlined,
            keyboardType: TextInputType.phone),
      ],
    );
  }
}

/// Edit sheet for a single address entry (label + value).
class _EditAddressSheet extends StatefulWidget {
  const _EditAddressSheet({this.existing});

  final Map<String, String>? existing;

  @override
  State<_EditAddressSheet> createState() => _EditAddressSheetState();
}

class _EditAddressSheetState extends State<_EditAddressSheet> {
  late final label =
      TextEditingController(text: widget.existing?['label'] ?? '');
  late final value =
      TextEditingController(text: widget.existing?['value'] ?? '');

  @override
  void dispose() {
    label.dispose();
    value.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return _SheetShell(
      title: widget.existing == null
          ? 'Ajouter une adresse'
          : 'Modifier l\'adresse',
      onSave: () {
        if (label.text.trim().isEmpty || value.text.trim().isEmpty) return;
        Navigator.pop(
            context, {'label': label.text.trim(), 'value': value.text.trim()});
      },
      children: [
        PremiumTextField(
            controller: label,
            label: 'Libellé (ex: Siège social)',
            icon: Icons.label_outline),
        const SizedBox(height: 12),
        PremiumTextField(
            controller: value,
            label: 'Adresse complète',
            icon: Icons.location_on_outlined),
      ],
    );
  }
}

/// Shared bottom-sheet shell (drag handle, title, field list, save button)
/// reused by all three edit sheets above.
class _SheetShell extends StatelessWidget {
  const _SheetShell(
      {required this.title, required this.children, required this.onSave});

  final String title;
  final List<Widget> children;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius:
              BorderRadius.vertical(top: Radius.circular(AppSpacing.radiusLg)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                    width: 36,
                    height: 4,
                    decoration: BoxDecoration(
                        color: AppColors.border,
                        borderRadius: BorderRadius.circular(2))),
              ),
              const SizedBox(height: 16),
              Text(title,
                  style: const TextStyle(
                      fontWeight: FontWeight.w900, fontSize: 16)),
              const SizedBox(height: 16),
              ...children,
              const SizedBox(height: 18),
              PremiumButton(label: 'Enregistrer', onPressed: onSave),
            ],
          ),
        ),
      ),
    );
  }
}
