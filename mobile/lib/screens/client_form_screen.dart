import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/country_dial_codes.dart';
import '../widgets/error_banner.dart';

class ClientFormScreen extends StatefulWidget {
  const ClientFormScreen({super.key, required this.api, this.client});

  final ApiClient api;
  final dynamic client;

  bool get isEditing => client != null;

  @override
  State<ClientFormScreen> createState() => _ClientFormScreenState();
}

class _ClientFormScreenState extends State<ClientFormScreen> {
  final formKey = GlobalKey<FormState>();
  late final raison = TextEditingController(text: widget.client?['raison_sociale']?.toString() ?? '');
  late final forme = TextEditingController(text: widget.client?['forme_juridique']?.toString() ?? '');
  late final ville = TextEditingController(text: widget.client?['ville']?.toString() ?? '');
  late final adresse = TextEditingController(text: widget.client?['adresse']?.toString() ?? '');
  late final pays = TextEditingController(text: widget.client?['pays']?.toString() ?? 'Maroc');
  late final capital = TextEditingController(text: widget.client?['capital']?.toString() ?? '');
  late final dateCreation = TextEditingController(text: widget.client?['date_creation']?.toString() ?? '');

  late final repNom = TextEditingController(text: rep?['nom']?.toString() ?? '');
  late final repPrenom = TextEditingController(text: rep?['prenom']?.toString() ?? '');
  late final repEmail = TextEditingController(text: rep?['email']?.toString() ?? '');
  late final repCin = TextEditingController(text: rep?['cin']?.toString() ?? '');
  late final repDateNaissance = TextEditingController(text: rep?['date_naissance']?.toString() ?? '');
  late final repAdresse = TextEditingController(text: rep?['adresse']?.toString() ?? '');
  late final repPhone = TextEditingController(text: splitPhone(rep?['telephone']?.toString()).number);
  late String repDialCode = splitPhone(rep?['telephone']?.toString()).dialCode;

  late final portalEmail = TextEditingController(
    text: widget.isEditing ? '' : (contact?['email']?.toString() ?? ''),
  );
  late final portalPassword = TextEditingController();

  bool saving = false;
  String? error;

  Map<String, dynamic>? get rep {
    final value = widget.client?['representant'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  Map<String, dynamic>? get contact {
    final value = widget.client?['clientUser'] ?? widget.client?['client_user'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  @override
  void dispose() {
    for (final controller in [
      raison,
      forme,
      ville,
      adresse,
      pays,
      capital,
      dateCreation,
      repNom,
      repPrenom,
      repEmail,
      repCin,
      repDateNaissance,
      repAdresse,
      repPhone,
      portalEmail,
      portalPassword,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    setState(() {
      saving = true;
      error = null;
    });

    try {
      final companyPayload = {
        'raison_sociale': raison.text.trim(),
        'forme_juridique': emptyToNull(forme.text),
        'adresse': emptyToNull(adresse.text),
        'ville': emptyToNull(ville.text),
        'pays': emptyToNull(pays.text),
        'capital': num.tryParse(capital.text.trim()),
        'date_creation': emptyToNull(dateCreation.text),
      };

      final response = widget.isEditing
          ? await widget.api.updateClient(widget.client['id'], companyPayload)
          : await widget.api.createClient({
              ...companyPayload,
              'client_nom': repNom.text.trim().isEmpty ? 'Non renseigne' : repNom.text.trim(),
              'client_prenom': emptyToNull(repPrenom.text),
              'client_email': portalEmail.text.trim(),
              'client_telephone': emptyToNull(joinPhone(repDialCode, repPhone.text)),
              'client_password': emptyToNull(portalPassword.text),
            });

      final entreprise = Map<String, dynamic>.from(response['data'] as Map);
      await widget.api.upsertRepresentant(entreprise['id'], {
        'nom': repNom.text.trim(),
        'prenom': emptyToNull(repPrenom.text),
        'cin': repCin.text.trim(),
        'date_naissance': emptyToNull(repDateNaissance.text),
        'adresse': emptyToNull(repAdresse.text),
        'telephone': emptyToNull(joinPhone(repDialCode, repPhone.text)),
        'email': emptyToNull(repEmail.text),
      });

      final fresh = await widget.api.getClient(entreprise['id']);

      if (!mounted) return;
      final generatedPassword = response['generated_password']?.toString();
      if (generatedPassword != null && generatedPassword.isNotEmpty) {
        await showDialog<void>(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Mot de passe genere'),
            content: SelectableText(generatedPassword),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer')),
            ],
          ),
        );
      }
      if (mounted) Navigator.pop(context, fresh);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Object? emptyToNull(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  Future<void> pickDate(TextEditingController controller) async {
    final current = DateTime.tryParse(controller.text.trim()) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(1900),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      controller.text = picked.toIso8601String().substring(0, 10);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.isEditing ? 'Modifier client' : 'Nouveau client')),
      body: Form(
        key: formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (error != null) ErrorBanner(message: error!),
            const SizedBox(height: 12),
            FormSection(
              icon: Icons.business_outlined,
              title: 'Entreprise',
              children: [
                AppTextField(controller: raison, label: 'Raison sociale *', required: true),
                Row(
                  children: [
                    Expanded(child: AppTextField(controller: forme, label: 'Forme juridique')),
                    const SizedBox(width: 12),
                    Expanded(child: AppTextField(controller: capital, label: 'Capital', keyboardType: TextInputType.number)),
                  ],
                ),
                AppTextField(controller: adresse, label: 'Adresse siege'),
                Row(
                  children: [
                    Expanded(child: AppTextField(controller: ville, label: 'Ville')),
                    const SizedBox(width: 12),
                    Expanded(child: AppTextField(controller: pays, label: 'Pays')),
                  ],
                ),
                AppTextField(controller: dateCreation, label: 'Date creation', readOnly: true, suffixIcon: Icons.calendar_today, onTap: () => pickDate(dateCreation)),
              ],
            ),
            FormSection(
              icon: Icons.badge_outlined,
              title: 'Representant legal',
              children: [
                Row(
                  children: [
                    Expanded(child: AppTextField(controller: repNom, label: 'Nom *', required: true)),
                    const SizedBox(width: 12),
                    Expanded(child: AppTextField(controller: repPrenom, label: 'Prenom')),
                  ],
                ),
                AppTextField(controller: repCin, label: 'CIN / Passeport *', required: true),
                AppTextField(controller: repDateNaissance, label: 'Date naissance', readOnly: true, suffixIcon: Icons.calendar_today, onTap: () => pickDate(repDateNaissance)),
                AppTextField(controller: repAdresse, label: 'Adresse de residence'),
                Row(
                  children: [
                    SizedBox(
                      width: 132,
                      child: DropdownButtonFormField<String>(
                        value: repDialCode,
                        decoration: const InputDecoration(labelText: 'Indicatif'),
                        items: uniqueCountryDialCodes()
                            .map((code) => DropdownMenuItem(
                                  value: code.dialCode,
                                  child: Text('${code.iso} ${code.dialCode}'),
                                ))
                            .toList(),
                        onChanged: (value) => setState(() => repDialCode = value ?? '+212'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: AppTextField(controller: repPhone, label: 'Telephone', keyboardType: TextInputType.phone)),
                  ],
                ),
                AppTextField(controller: repEmail, label: 'Email representant', keyboardType: TextInputType.emailAddress),
              ],
            ),
            if (!widget.isEditing)
              FormSection(
                icon: Icons.lock_person_outlined,
                title: 'Acces portail client',
                children: [
                  AppTextField(controller: portalEmail, label: 'Email portail *', required: true, keyboardType: TextInputType.emailAddress),
                  AppTextField(controller: portalPassword, label: 'Mot de passe portail', helper: 'Laisser vide pour generation automatique'),
                ],
              ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: saving ? null : save,
              icon: const Icon(Icons.save_outlined),
              label: Text(saving ? 'Enregistrement...' : 'Enregistrer'),
            ),
          ],
        ),
      ),
    );
  }
}

class FormSection extends StatelessWidget {
  const FormSection({super.key, required this.icon, required this.title, required this.children});

  final IconData icon;
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
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

class AppTextField extends StatelessWidget {
  const AppTextField({
    super.key,
    required this.controller,
    required this.label,
    this.required = false,
    this.keyboardType,
    this.helper,
    this.readOnly = false,
    this.suffixIcon,
    this.onTap,
  });

  final TextEditingController controller;
  final String label;
  final bool required;
  final TextInputType? keyboardType;
  final String? helper;
  final bool readOnly;
  final IconData? suffixIcon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        readOnly: readOnly,
        onTap: onTap,
        decoration: InputDecoration(
          labelText: label,
          helperText: helper,
          suffixIcon: suffixIcon == null ? null : Icon(suffixIcon),
        ),
        validator: required
            ? (value) => value == null || value.trim().isEmpty ? 'Champ obligatoire' : null
            : null,
      ),
    );
  }
}
