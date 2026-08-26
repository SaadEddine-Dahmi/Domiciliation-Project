import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';

class ContractFormScreen extends StatefulWidget {
  const ContractFormScreen({super.key, required this.api, this.initialClient});

  final ApiClient api;
  final dynamic initialClient;

  @override
  State<ContractFormScreen> createState() => _ContractFormScreenState();
}

class _ContractFormScreenState extends State<ContractFormScreen> {
  final formKey = GlobalKey<FormState>();
  final title = TextEditingController(text: 'Contrat de Domiciliation ');
  final dateDebut = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
  final dateFin = TextEditingController();
  final duree = TextEditingController(text: '12');
  final prixMensuel = TextEditingController();
  final prixTotal = TextEditingController();
  final caution = TextEditingController();
  final instructionNo = TextEditingController();
  final villeSignature = TextEditingController();
  final dateSignature = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));

  int step = 0;
  List<dynamic> clients = [];
  List<dynamic> articles = [];
  List<dynamic> templates = [];
  List<ContractAddress> addresses = [];
  final selectedArticles = <Object, dynamic>{};
  Object? selectedClientId;
  Object? selectedTemplateId;
  String selectedAddress = '';
  String modePaiement = 'Virement';
  bool loading = true;
  bool saving = false;
  bool syncingAmounts = false;
  String? error;

  @override
  void initState() {
    super.initState();
    selectedClientId = widget.initialClient?['id'];
    dateDebut.addListener(syncEndDateFromStart);
    prixMensuel.addListener(syncAnnualFromMonthly);
    prixTotal.addListener(syncMonthlyFromAnnual);
    loadData();
  }

  Future<void> loadData() async {
    try {
      final loadedClients = await widget.api.list('/api/clients');
      final loadedArticles = await widget.api.list('/api/articles');
      final loadedTemplates = await widget.api.list('/api/templates');
      final loadedProfile = await widget.api.profile();
      setState(() {
        clients = loadedClients;
        articles = loadedArticles;
        templates = loadedTemplates;
        addresses = normaliseAddresses(loadedProfile['adresses'] ?? loadedProfile['addresses'] ?? loadedProfile['adresse'] ?? loadedProfile['address']);
        selectedAddress = addresses.isNotEmpty ? addresses.first.value : '';
        selectedClientId ??= loadedClients.isEmpty ? null : loadedClients.first['id'];
        syncEndDateFromStart();
        loading = false;
      });
    } catch (e) {
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loading = false;
      });
    }
  }

  @override
  void dispose() {
    dateDebut.removeListener(syncEndDateFromStart);
    prixMensuel.removeListener(syncAnnualFromMonthly);
    prixTotal.removeListener(syncMonthlyFromAnnual);
    for (final controller in [
      title,
      dateDebut,
      dateFin,
      duree,
      prixMensuel,
      prixTotal,
      caution,
      instructionNo,
      villeSignature,
      dateSignature,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  List<ContractAddress> normaliseAddresses(dynamic raw) {
    if (raw is List) {
      return raw.asMap().entries.map((entry) {
        final item = entry.value;
        if (item is String) return ContractAddress('Adresse ${entry.key + 1}', item);
        if (item is Map) {
          return ContractAddress(
            '${item['label'] ?? item['nom'] ?? item['name'] ?? 'Adresse ${entry.key + 1}'}',
            '${item['value'] ?? item['adresse'] ?? item['address'] ?? ''}',
          );
        }
        return ContractAddress('Adresse ${entry.key + 1}', '$item');
      }).where((item) => item.value.trim().isNotEmpty).toList();
    }
    if (raw is String && raw.trim().isNotEmpty) {
      return [ContractAddress('Adresse principale', raw.trim())];
    }
    return [];
  }

  void syncEndDateFromStart() {
    final start = DateTime.tryParse(dateDebut.text.trim());
    if (start == null) return;
    final next = DateTime(start.year + 1, start.month, start.day);
    final value = next.toIso8601String().substring(0, 10);
    if (dateFin.text != value) dateFin.text = value;
  }

  void syncAnnualFromMonthly() {
    if (syncingAmounts) return;
    final monthly = double.tryParse(prixMensuel.text.replaceAll(',', '.'));
    if (monthly == null) return;
    syncingAmounts = true;
    prixTotal.text = (monthly * 12).toStringAsFixed(2);
    syncingAmounts = false;
  }

  void syncMonthlyFromAnnual() {
    if (syncingAmounts) return;
    final annual = double.tryParse(prixTotal.text.replaceAll(',', '.'));
    if (annual == null) return;
    syncingAmounts = true;
    prixMensuel.text = (annual / 12).toStringAsFixed(2);
    syncingAmounts = false;
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate() || selectedAddress.trim().isEmpty || selectedClientId == null || selectedArticles.isEmpty) {
      setState(() {
        if (selectedAddress.trim().isEmpty) {
          step = 0;
        } else if (selectedClientId == null) {
          step = 1;
        } else if (selectedArticles.isEmpty) {
          step = 2;
        }
      });
      return;
    }

    setState(() {
      saving = true;
      error = null;
    });

    try {
      final created = await widget.api.createContract(buildPayload());
      if (!mounted) return;
      var closeFormAfterDialog = true;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Brouillon cree'),
          content: SizedBox(
            width: 420,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Le contrat est pret pour apercu PDF, signature et legalisation.'),
                const SizedBox(height: 14),
                _DocumentPreviewBanner(title: title.text),
                const SizedBox(height: 12),
                FilledButton.icon(
                  onPressed: () {
                    closeFormAfterDialog = false;
                    Navigator.pop(context);
                    showPreview();
                  },
                  icon: const Icon(Icons.visibility_outlined),
                  label: const Text('Voir l apercu'),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () {
                    closeFormAfterDialog = false;
                    openExternal(widget.api.contractPdfUrl(created['id']));
                  },
                  icon: const Icon(Icons.download_outlined),
                  label: const Text('Telecharger PDF'),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer')),
          ],
        ),
      );
      if (mounted && closeFormAfterDialog) Navigator.pop(context, created);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> pickDate(TextEditingController controller) async {
    final current = DateTime.tryParse(controller.text.trim()) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      controller.text = picked.toIso8601String().substring(0, 10);
      setState(() {});
    }
  }

  Map<String, dynamic> buildPayload() {
    return {
      'entreprise_id': selectedClientId,
      'titre_contrat': title.text.trim(),
      'date_debut': dateDebut.text.trim(),
      'date_fin': dateFin.text.trim(),
      'duree_mois': int.tryParse(duree.text.trim()),
      'prix_mensuel': num.tryParse(prixMensuel.text.trim().replaceAll(',', '.')),
      'prix_total': num.tryParse(prixTotal.text.trim().replaceAll(',', '.')),
      'instruction_no': instructionNo.text.trim(),
      'ville_signature': villeSignature.text.trim(),
      'date_signature': dateSignature.text.trim(),
      if (caution.text.trim().isNotEmpty) 'caution': num.tryParse(caution.text.trim()),
      'mode_paiement': modePaiement,
      'articles': selectedArticles.values
          .toList()
          .asMap()
          .entries
          .map((entry) => {'id': '${entry.value['id']}', 'ordre': entry.key + 1})
          .toList(),
    };
  }

  void showPreview() {
    dynamic client;
    for (final row in clients) {
      if (row['id'] == selectedClientId) {
        client = row;
        break;
      }
    }
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.72,
        maxChildSize: 0.92,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          children: [
            Text('Apercu avant soumission', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            PreviewRow(label: 'Client', value: '${client?['raison_sociale'] ?? '-'}'),
            PreviewRow(label: 'Titre', value: title.text),
            PreviewRow(label: 'Periode', value: '${dateDebut.text} - ${dateFin.text.isEmpty ? 'non definie' : dateFin.text}'),
            PreviewRow(label: 'Duree', value: '${duree.text} mois'),
            PreviewRow(label: 'Prix mensuel', value: prixMensuel.text.isEmpty ? '-' : '${prixMensuel.text} DH'),
            PreviewRow(label: 'Prix total', value: prixTotal.text.isEmpty ? '-' : '${prixTotal.text} DH'),
            PreviewRow(label: 'Caution', value: caution.text.isEmpty ? '-' : '${caution.text} DH'),
            PreviewRow(label: 'Paiement', value: modePaiement),
            PreviewRow(label: 'Signature', value: '${villeSignature.text}, ${dateSignature.text}'),
            const Divider(height: 28),
            Text('Articles (${selectedArticles.length})', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            for (final article in selectedArticles.values)
              ListTile(
                dense: true,
                leading: const Icon(Icons.article_outlined),
                title: Text('${article['title'] ?? article['titre'] ?? 'Article'}'),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nouveau contrat')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: formKey,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  _StepProgress(step: step, onSelect: (value) => setState(() => step = value)),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    ErrorBanner(message: error!),
                  ],
                  const SizedBox(height: 16),
                  _stepContent(context),
                  const SizedBox(height: 96),
                ],
              ),
            ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: step == 0 ? null : () => setState(() => step -= 1),
                  icon: const Icon(Icons.chevron_left),
                  label: const Text('Retour'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton.icon(
                  onPressed: step >= 3 ? (saving ? null : save) : () => setState(() => step += 1),
                  icon: Icon(step >= 3 ? Icons.check : Icons.chevron_right),
                  label: Text(step >= 3 ? 'Creer' : 'Suivant'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _stepContent(BuildContext context) {
    if (step == 0) {
      return Column(
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Domiciliataire', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 8),
                const Text('Adresse, titre et base du contrat.', style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 16),
                ContractField(controller: title, label: 'Titre du contrat *', required: true),
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  value: selectedAddress.isEmpty ? null : selectedAddress,
                  decoration: const InputDecoration(labelText: 'Adresse de domiciliation *', prefixIcon: Icon(Icons.location_on_outlined)),
                  selectedItemBuilder: (context) => addresses
                      .map((addr) => Align(
                            alignment: Alignment.centerLeft,
                            child: Text(
                              '${addr.label} - ${addr.value}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ))
                      .toList(),
                  items: addresses
                      .map((addr) => DropdownMenuItem<String>(
                            value: addr.value,
                            child: Text(
                              '${addr.label} - ${addr.value}',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ))
                      .toList(),
                  onChanged: (value) => setState(() => selectedAddress = value ?? ''),
                  validator: (value) => value == null || value.trim().isEmpty ? 'Champ obligatoire' : null,
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          _DocumentPreviewBanner(title: title.text),
        ],
      );
    }

    if (step == 1) {
      return PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Client', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            const Text('Selectionnez le client rattache au contrat.', style: TextStyle(color: AppColors.muted)),
            const SizedBox(height: 14),
            DropdownButtonFormField<Object>(
              isExpanded: true,
              value: selectedClientId,
              decoration: const InputDecoration(labelText: 'Client *', prefixIcon: Icon(Icons.business_outlined)),
              items: clients
                  .map((client) => DropdownMenuItem<Object>(
                        value: client['id'],
                        child: Text('${client['raison_sociale'] ?? 'Client'}', overflow: TextOverflow.ellipsis),
                      ))
                  .toList(),
              onChanged: (value) => setState(() => selectedClientId = value),
              validator: (value) => value == null ? 'Champ obligatoire' : null,
            ),
          ],
        ),
      );
    }

    if (step == 2) {
      final selected = selectedArticles.values.toList();
      return PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Articles, montants et signature', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            const Text('Choisissez les clauses et reorganisez leur ordre.', style: TextStyle(color: AppColors.muted)),
            const SizedBox(height: 14),
            if (templates.isNotEmpty)
              DropdownButtonFormField<Object>(
                isExpanded: true,
                value: selectedTemplateId,
                decoration: const InputDecoration(labelText: 'Charger un template', prefixIcon: Icon(Icons.dashboard)),
                items: templates
                    .map((template) => DropdownMenuItem<Object>(
                          value: template['id'],
                          child: Text('${template['name'] ?? 'Template'}', overflow: TextOverflow.ellipsis),
                        ))
                    .toList(),
                onChanged: (value) {
                  dynamic template;
                  for (final item in templates) {
                    if (item['id'] == value) {
                      template = item;
                      break;
                    }
                  }
                  if (template == null) return;
                  final rows = List<dynamic>.from(template['articles'] as List? ?? const []);
                  rows.sort((a, b) => ((a['pivot']?['ordre'] ?? 0) as num).compareTo((b['pivot']?['ordre'] ?? 0) as num));
                  setState(() {
                    selectedTemplateId = value;
                    selectedArticles
                      ..clear()
                      ..addEntries(rows.map((article) => MapEntry(article['id'] as Object, article)));
                  });
                },
              ),
            if (templates.isNotEmpty) const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final article in articles)
                  FilterChip(
                    selected: selectedArticles.containsKey(article['id']),
                    label: Text('${article['title'] ?? article['titre'] ?? 'Article'}'),
                    avatar: Icon(selectedArticles.containsKey(article['id']) ? Icons.check_circle_outline : Icons.add_circle_outline, size: 17),
                    onSelected: (checked) {
                      setState(() {
                        if (checked) {
                          selectedArticles[article['id']] = article;
                        } else {
                          selectedArticles.remove(article['id']);
                        }
                      });
                    },
                  ),
              ],
            ),
            const SizedBox(height: 18),
            Text('Clauses selectionnees (${selected.length})', style: const TextStyle(fontWeight: FontWeight.w900)),
            const SizedBox(height: 10),
            ReorderableListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: selected.length,
              onReorder: (oldIndex, newIndex) {
                if (newIndex > oldIndex) newIndex -= 1;
                final rows = selectedArticles.entries.toList();
                final moved = rows.removeAt(oldIndex);
                rows.insert(newIndex, moved);
                setState(() {
                  selectedArticles
                    ..clear()
                    ..addEntries(rows);
                });
              },
              itemBuilder: (context, index) {
                final article = selected[index];
                return ListTile(
                  key: ValueKey(article['id']),
                  leading: CircleAvatar(backgroundColor: AppColors.primaryGold, child: Text('${index + 1}', style: const TextStyle(color: AppColors.background, fontWeight: FontWeight.w900))),
                  title: Text('${article['title'] ?? article['titre'] ?? 'Article'}'),
                  subtitle: article['body'] == null ? null : Text('${article['body']}', maxLines: 2, overflow: TextOverflow.ellipsis),
                  trailing: IconButton(icon: const Icon(Icons.delete_outline, color: AppColors.red), onPressed: () => setState(() => selectedArticles.remove(article['id']))),
                );
              },
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(child: ContractField(controller: dateDebut, label: 'Date debut *', required: true, readOnly: true, suffixIcon: Icons.calendar_today, onTap: () => pickDate(dateDebut))),
                const SizedBox(width: 12),
                Expanded(child: ContractField(controller: dateFin, label: 'Date fin *', required: true, readOnly: true, suffixIcon: Icons.calendar_today, onTap: () => pickDate(dateFin))),
              ],
            ),
            Row(
              children: [
                Expanded(child: ContractField(controller: duree, label: 'Duree mois *', required: true, keyboardType: TextInputType.number)),
                const SizedBox(width: 12),
                Expanded(child: ContractField(controller: instructionNo, label: 'Instruction no *', required: true)),
              ],
            ),
            Row(
              children: [
                Expanded(child: ContractField(controller: prixMensuel, label: 'Prix mensuel *', required: true, keyboardType: TextInputType.number)),
                const SizedBox(width: 12),
                Expanded(child: ContractField(controller: prixTotal, label: 'Prix annuel *', required: true, keyboardType: TextInputType.number)),
              ],
            ),
            Row(
              children: [
                Expanded(child: ContractField(controller: caution, label: 'Caution', keyboardType: TextInputType.number)),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    isExpanded: true,
                    value: modePaiement,
                    decoration: const InputDecoration(labelText: 'Mode paiement *'),
                    items: const ['Virement', 'Especes', 'Cheque', 'Carte bancaire'].map((value) => DropdownMenuItem(value: value, child: Text(value))).toList(),
                    onChanged: (value) => setState(() => modePaiement = value ?? 'Virement'),
                  ),
                ),
              ],
            ),
            ContractField(controller: villeSignature, label: 'Ville de signature *', required: true),
            ContractField(controller: dateSignature, label: 'Date de signature *', required: true, readOnly: true, suffixIcon: Icons.calendar_today, onTap: () => pickDate(dateSignature)),
          ],
        ),
      );
    }

    if (step == 3) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _DocumentPreviewBanner(title: title.text),
          const SizedBox(height: 14),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Confirmation', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 14),
                FilledButton.tonalIcon(onPressed: showPreview, icon: const Icon(Icons.visibility_outlined), label: const Text('Voir l apercu dans l app')),
                const SizedBox(height: 12),
                FilledButton.icon(onPressed: saving ? null : save, icon: const Icon(Icons.save_outlined), label: Text(saving ? 'Creation...' : 'Creer le brouillon')),
              ],
            ),
          ),
        ],
      );
    }

    return const SizedBox.shrink();
  }
}

class _StepProgress extends StatelessWidget {
  const _StepProgress({required this.step, required this.onSelect});

  final int step;
  final void Function(int step) onSelect;

  @override
  Widget build(BuildContext context) {
    const labels = ['Domiciliataire', 'Client', 'Articles', 'Confirmation'];
    return Row(
      children: [
        for (var i = 0; i < labels.length; i++) ...[
          Expanded(
            child: InkWell(
              onTap: () => onSelect(i),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 16,
                    backgroundColor: i <= step ? AppColors.primaryGold : AppColors.surfaceRaised,
                    child: Text('${i + 1}', style: TextStyle(color: i <= step ? AppColors.background : AppColors.text, fontWeight: FontWeight.w900)),
                  ),
                  const SizedBox(height: 6),
                  Text(labels[i], maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11, color: i == step ? AppColors.primaryGold : AppColors.muted)),
                ],
              ),
            ),
          ),
          if (i < labels.length - 1)
            Expanded(
              child: Container(
                height: 2,
                margin: const EdgeInsets.only(bottom: 22),
                color: i < step ? AppColors.primaryGold : AppColors.border,
              ),
            ),
        ],
      ],
    );
  }
}

class _DocumentPreviewBanner extends StatelessWidget {
  const _DocumentPreviewBanner({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.primaryGold,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          const Icon(Icons.description_outlined, color: AppColors.background, size: 34),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              title.isEmpty ? 'CONTRAT DE DOMICILIATION' : title.toUpperCase(),
              style: const TextStyle(color: AppColors.background, fontFamily: 'Fraunces', fontWeight: FontWeight.w900, fontSize: 16),
            ),
          ),
        ],
      ),
    );
  }
}

class PreviewRow extends StatelessWidget {
  const PreviewRow({super.key, required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 112, child: Text(label, style: Theme.of(context).textTheme.bodySmall)),
          Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w700))),
        ],
      ),
    );
  }
}

class ContractAddress {
  const ContractAddress(this.label, this.value);

  final String label;
  final String value;
}

class ContractField extends StatelessWidget {
  const ContractField({
    super.key,
    required this.controller,
    required this.label,
    this.required = false,
    this.keyboardType,
    this.readOnly = false,
    this.suffixIcon,
    this.onTap,
  });

  final TextEditingController controller;
  final String label;
  final bool required;
  final TextInputType? keyboardType;
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
          suffixIcon: suffixIcon == null ? null : Icon(suffixIcon),
        ),
        validator: required
            ? (value) => value == null || value.trim().isEmpty ? 'Champ obligatoire' : null
            : null,
      ),
    );
  }
}
