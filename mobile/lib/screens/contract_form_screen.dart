// lib/screens/contract_form_screen.dart
//
// 4-step contract creation wizard, matching the approved mockup:
//   1. Informations — dynamic contract title (freely typed by the
//      domiciliataire, live-previewed) + domiciliation address selector.
//   2. Clauses — chip picker over the article library, then a
//      reorderable "Clauses sélectionnées" list (drag handle, numbered
//      badge, expand-to-preview, delete).
//   3. Parties — pick an existing client or create one inline.
//   4. Récapitulatif — read-only summary + submit.
//
// ── titre_contrat is fully dynamic ──────────────────────────────────────
//   The domiciliataire types whatever title they want printed at the top
//   of the contract PDF (e.g. "Contrat de Domiciliation", "Convention de
//   Domiciliation Commerciale"...). Sent to the backend as-is; the backend
//   (ContratController::store) applies the 'Contrat de Domiciliation'
//   fallback ONLY when the field arrives null/empty. No default is
//   hardcoded here — this mirrors contrat.vue on the web exactly.
//
// ── Article selection ───────────────────────────────────────────────────
//   selectedIds is a List<String> (not a Set) so state updates are always
//   explicit reassignments — avoids the "all chips highlighted" class of
//   bug documented in the web wizard's comments (stale shared references
//   when mutating a Set/flag in place). Every article id is normalized to
//   String() the moment it's read from the API response.
//
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/gold_avatar.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_card.dart';
import 'client_form_screen.dart';

class ContractFormScreen extends StatefulWidget {
  const ContractFormScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ContractFormScreen> createState() => _ContractFormScreenState();
}

class _ContractFormScreenState extends State<ContractFormScreen> {
  static const steps = ['Informations', 'Clauses', 'Parties', 'Récapitulatif'];

  int step = 0;
  bool loadingInitial = true;
  bool saving = false;
  String? error;
  dynamic createdContract;

  // ── Step 1: title + address ──────────────────────────────────────────
  final titleController = TextEditingController(text: 'Contrat de domiciliation');
  List<_AddressOption> addresses = [];
  String? selectedAddress;

  // ── Step 2: articles ─────────────────────────────────────────────────
  List<dynamic> articleLibrary = [];
  final List<String> selectedIds = [];
  final List<Map<String, dynamic>> orderedArticles = [];
  final Set<String> expandedIds = {};

  // ── Step 3: client ───────────────────────────────────────────────────
  List<dynamic> clients = [];
  dynamic selectedClient;
  final clientSearch = TextEditingController();

  // ── Step 3/4: dates & pricing ────────────────────────────────────────
  DateTime dateDebut = DateTime.now();
  late DateTime dateFin;
  DateTime dateSignature = DateTime.now();
  int months = 12;
  final monthsController = TextEditingController(text: '12');
  final monthlyController = TextEditingController(text: '0');
  final annualController = TextEditingController(text: '0');
  final cautionController = TextEditingController();
  final instructionController = TextEditingController();
  final villeSignatureController = TextEditingController();
  String modePaiement = '';

  double get monthlyAmount => double.tryParse(monthlyController.text.replaceAll(',', '.')) ?? 0;
  double get annualAmount => double.tryParse(annualController.text.replaceAll(',', '.')) ?? 0;
  double get cautionAmount => double.tryParse(cautionController.text.replaceAll(',', '.')) ?? 0;
  double get totalAmount => annualAmount;

  String _isoDate(DateTime date) => date.toIso8601String().substring(0, 10);

  @override
  void initState() {
    super.initState();
    dateFin = DateTime(dateDebut.year, dateDebut.month + months, dateDebut.day);
    loadInitial();
  }

  @override
  void dispose() {
    titleController.dispose();
    clientSearch.dispose();
    monthsController.dispose();
    monthlyController.dispose();
    annualController.dispose();
    cautionController.dispose();
    instructionController.dispose();
    villeSignatureController.dispose();
    super.dispose();
  }

  Future<void> loadInitial() async {
    setState(() {
      loadingInitial = true;
      error = null;
    });
    try {
      final profile = await widget.api.profile();
      final loadedArticles = await widget.api.list('/api/articles');
      final loadedClients = await widget.api.list('/api/clients');

      if (!mounted) return;
      setState(() {
        addresses = _normaliseAddresses(profile['adresses'] ?? profile['addresses']);
        if (addresses.isNotEmpty) selectedAddress ??= addresses.first.value;
        villeSignatureController.text = '${profile['ville'] ?? profile['city'] ?? ''}';
        articleLibrary = loadedArticles;
        clients = loadedClients;
        loadingInitial = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loadingInitial = false;
      });
    }
  }

  List<_AddressOption> _normaliseAddresses(dynamic raw) {
    if (raw is! List) return const [];
    return raw.asMap().entries.map((entry) {
      final item = entry.value;
      if (item is Map) {
        final value = '${item['value'] ?? item['adresse'] ?? item['address'] ?? ''}';
        final label = '${item['label'] ?? item['nom'] ?? 'Adresse ${entry.key + 1}'}';
        return _AddressOption(label, value);
      }
      return _AddressOption('Adresse ${entry.key + 1}', '$item');
    }).where((a) => a.value.trim().isNotEmpty).toList();
  }

  // ── Article selection ────────────────────────────────────────────────

  void toggleArticle(dynamic article) {
    final id = '${article['id']}';
    setState(() {
      if (selectedIds.contains(id)) {
        selectedIds.remove(id);
        orderedArticles.removeWhere((a) => '${a['id']}' == id);
        for (var i = 0; i < orderedArticles.length; i++) {
          orderedArticles[i]['ordre'] = i + 1;
        }
      } else {
        selectedIds.add(id);
        orderedArticles.add({
          ...Map<String, dynamic>.from(article as Map),
          'ordre': orderedArticles.length + 1,
        });
      }
    });
  }

  void reorderArticles(int oldIndex, int newIndex) {
    setState(() {
      if (newIndex > oldIndex) newIndex -= 1;
      final moved = orderedArticles.removeAt(oldIndex);
      orderedArticles.insert(newIndex, moved);
      for (var i = 0; i < orderedArticles.length; i++) {
        orderedArticles[i]['ordre'] = i + 1;
      }
    });
  }

  void setDateDebut(DateTime value) {
    setState(() {
      dateDebut = value;
      dateFin = DateTime(dateDebut.year, dateDebut.month + months, dateDebut.day);
    });
  }

  void setDateFin(DateTime value) {
    setState(() {
      dateFin = value;
      final calculated = (dateFin.year - dateDebut.year) * 12 + dateFin.month - dateDebut.month;
      months = calculated < 1 ? 1 : calculated;
      monthsController.text = '$months';
      syncAnnualFromMonthly();
    });
  }

  void setMonths(int value) {
    setState(() {
      months = value < 1 ? 1 : value;
      monthsController.text = '$months';
      dateFin = DateTime(dateDebut.year, dateDebut.month + months, dateDebut.day);
      syncAnnualFromMonthly();
    });
  }

  void syncAnnualFromMonthly() {
    annualController.text = (monthlyAmount * months).toStringAsFixed(2);
  }

  void syncMonthlyFromAnnual() {
    monthlyController.text = (months > 0 ? annualAmount / months : annualAmount).toStringAsFixed(2);
  }

  // ── Navigation ────────────────────────────────────────────────────────

  bool canProceed() {
    if (step == 0) return titleController.text.trim().isNotEmpty && selectedAddress != null;
    if (step == 2) {
      return selectedClient != null &&
          villeSignatureController.text.trim().isNotEmpty &&
          !dateFin.isBefore(dateDebut);
    }
    return true;
  }

  void next() {
    if (!canProceed()) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(step == 0 ? 'Sélectionnez une adresse de domiciliation' : 'Complétez les champs obligatoires')),
      );
      return;
    }
    if (step == steps.length - 1) {
      submit();
      return;
    }
    setState(() => step++);
  }

  void previous() {
    if (step > 0) setState(() => step--);
  }

  Future<void> submit() async {
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final payload = {
        'entreprise_id': selectedClient['id'],
        'titre_contrat': titleController.text.trim().isEmpty ? null : titleController.text.trim(),
        'date_debut': _isoDate(dateDebut),
        'date_fin': _isoDate(dateFin),
        'duree_mois': months,
        'prix_mensuel': monthlyAmount,
        'prix_total': totalAmount,
        'instruction_no': instructionController.text.trim().isEmpty ? null : instructionController.text.trim(),
        'ville_signature': villeSignatureController.text.trim(),
        'date_signature': _isoDate(dateSignature),
        'caution': cautionController.text.trim().isEmpty ? null : cautionAmount,
        'mode_paiement': modePaiement.isEmpty ? null : modePaiement,
        'statut': 'draft',
        'articles': orderedArticles
            .map((a) => {'id': '${a['id']}', 'ordre': a['ordre']})
            .toList(),
      };

      final result = await widget.api.createContract(payload);
      if (!mounted) return;
      setState(() {
        createdContract = result;
        saving = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        saving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (createdContract != null) {
      return _ContractCreatedView(
        api: widget.api,
        contract: createdContract,
        onDone: () => Navigator.pop(context, createdContract),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Nouveau contrat')),
      body: loadingInitial
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                _StepHeader(steps: steps, current: step),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                    child: ErrorBanner(message: error!),
                  ),
                Expanded(child: _buildStepBody()),
                _buildFooter(),
              ],
            ),
    );
  }

  Widget _buildStepBody() {
    switch (step) {
      case 0:
        return _InformationsStep(
          titleController: titleController,
          addresses: addresses,
          selectedAddress: selectedAddress,
          onSelectAddress: (v) => setState(() => selectedAddress = v),
        );
      case 1:
        return _ClausesStep(
          library: articleLibrary,
          selectedIds: selectedIds,
          orderedArticles: orderedArticles,
          expandedIds: expandedIds,
          onToggle: toggleArticle,
          onReorder: reorderArticles,
          onToggleExpand: (id) => setState(() {
            if (expandedIds.contains(id)) {
              expandedIds.remove(id);
            } else {
              expandedIds.add(id);
            }
          }),
        );
      case 2:
        return _PartiesStep(
          api: widget.api,
          clients: clients,
          search: clientSearch,
          selectedClient: selectedClient,
          onSelect: (c) => setState(() => selectedClient = c),
          onCreated: (c) async {
            setState(() {
              clients = [c, ...clients];
              selectedClient = c;
            });
          },
          dateDebut: dateDebut,
          dateFin: dateFin,
          dateSignature: dateSignature,
          months: months,
          monthsController: monthsController,
          monthlyController: monthlyController,
          annualController: annualController,
          cautionController: cautionController,
          instructionController: instructionController,
          villeSignatureController: villeSignatureController,
          modePaiement: modePaiement,
          onDateDebutChanged: setDateDebut,
          onDateFinChanged: setDateFin,
          onDateSignatureChanged: (d) => setState(() => dateSignature = d),
          onMonthsChanged: setMonths,
          onMonthlyChanged: () => setState(syncAnnualFromMonthly),
          onAnnualChanged: () => setState(syncMonthlyFromAnnual),
          onModePaiementChanged: (v) => setState(() => modePaiement = v),
        );
      case 3:
      default:
        return _RecapStep(
          title: titleController.text.trim(),
          address: selectedAddress ?? '',
          client: selectedClient,
          articles: orderedArticles,
          dateDebut: dateDebut,
          dateFin: dateFin,
          months: months,
          monthly: monthlyAmount,
          total: totalAmount,
          caution: cautionController.text.trim(),
          instructionNo: instructionController.text.trim(),
          villeSignature: villeSignatureController.text.trim(),
          dateSignature: dateSignature,
          modePaiement: modePaiement,
        );
    }
  }

  Widget _buildFooter() {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
        child: Row(
          children: [
            if (step > 0)
              Expanded(
                child: OutlinedButton(onPressed: saving ? null : previous, child: const Text('Précédent')),
              ),
            if (step > 0) const SizedBox(width: 12),
            Expanded(
              flex: 2,
              child: PremiumButton(
                onPressed: next,
                loading: saving,
                icon: step == steps.length - 1 ? Icons.check_circle_outline : Icons.arrow_forward,
                label: step == steps.length - 1 ? (saving ? 'Création...' : 'Créer le contrat') : 'Suivant',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AddressOption {
  const _AddressOption(this.label, this.value);
  final String label;
  final String value;
}

/// Numbered step indicator connected by a line — matches the mockup's
/// "① Informations — ② Clauses — ③ Parties — ④ Récapitulatif" header.
class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.steps, required this.current});

  final List<String> steps;
  final int current;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
      child: Row(
        children: [
          for (var i = 0; i < steps.length; i++) ...[
            _StepDot(index: i + 1, label: steps[i], active: i <= current),
            if (i != steps.length - 1)
              Expanded(
                child: Container(
                  height: 1.4,
                  margin: const EdgeInsets.only(bottom: 20),
                  color: i < current ? AppColors.primaryGold : AppColors.border,
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _StepDot extends StatelessWidget {
  const _StepDot({required this.index, required this.label, required this.active});

  final int index;
  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: active ? AppColors.primaryGold : Colors.transparent,
            border: Border.all(color: active ? AppColors.primaryGold : AppColors.border, width: 1.4),
          ),
          child: Text(
            '$index',
            style: TextStyle(
              color: active ? AppColors.background : AppColors.muted,
              fontWeight: FontWeight.w900,
              fontSize: 12.5,
            ),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          label,
          style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w800,
            color: active ? AppColors.primaryGold : AppColors.faint,
          ),
        ),
      ],
    );
  }
}

// ── Step 1: Informations ───────────────────────────────────────────────

class _InformationsStep extends StatelessWidget {
  const _InformationsStep({
    required this.titleController,
    required this.addresses,
    required this.selectedAddress,
    required this.onSelectAddress,
  });

  final TextEditingController titleController;
  final List<_AddressOption> addresses;
  final String? selectedAddress;
  final ValueChanged<String> onSelectAddress;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      children: [
        const Text('Titre du contrat', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 4),
        const Text(
          "Saisissez le titre qui apparaîtra en en-tête du contrat",
          style: TextStyle(color: AppColors.muted, fontSize: 12.5),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: titleController,
          onChanged: (_) => (context as Element).markNeedsBuild(),
          decoration: const InputDecoration(hintText: 'Contrat de domiciliation'),
        ),
        const SizedBox(height: 16),
        const Text('Aperçu en temps réel', style: TextStyle(color: AppColors.muted, fontSize: 12)),
        const SizedBox(height: 8),
        AnimatedBuilder(
          animation: titleController,
          builder: (context, _) => Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 18),
            decoration: BoxDecoration(
              color: AppColors.primaryGold,
              borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            ),
            child: Column(
              children: [
                const Icon(Icons.description_outlined, color: AppColors.background),
                const SizedBox(height: 6),
                Text(
                  titleController.text.trim().isEmpty
                      ? 'CONTRAT DE DOMICILIATION'
                      : titleController.text.trim().toUpperCase(),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: 'Fraunces',
                    fontWeight: FontWeight.w800,
                    color: AppColors.background,
                    fontSize: 15,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 26),
        const Text('Adresse de domiciliation', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 4),
        const Text(
          "Sélectionnez l'adresse à utiliser dans le contrat",
          style: TextStyle(color: AppColors.muted, fontSize: 12.5),
        ),
        const SizedBox(height: 12),
        if (addresses.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 14),
            child: Text('Aucune adresse enregistrée dans votre profil.', style: TextStyle(color: AppColors.amber)),
          )
        else
          for (final addr in addresses)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _AddressCard(
                option: addr,
                selected: selectedAddress == addr.value,
                onTap: () => onSelectAddress(addr.value),
              ),
            ),
      ],
    );
  }
}

class _AddressCard extends StatelessWidget {
  const _AddressCard({required this.option, required this.selected, required this.onTap});

  final _AddressOption option;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: selected ? AppColors.primaryGold.withOpacity(0.10) : AppColors.surfaceRaised,
            borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            border: Border.all(color: selected ? AppColors.primaryGold : AppColors.border, width: selected ? 1.4 : 1),
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: AppColors.primaryGold.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.apartment_outlined, size: 18, color: AppColors.primaryGold),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(option.label, style: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w800, fontSize: 13.5)),
                    const SizedBox(height: 2),
                    Text(option.value, style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                  ],
                ),
              ),
              Icon(
                selected ? Icons.check_circle : Icons.radio_button_unchecked,
                color: selected ? AppColors.primaryGold : AppColors.faint,
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Step 2: Clauses ──────────────────────────────────────────────────────

class _ClausesStep extends StatelessWidget {
  const _ClausesStep({
    required this.library,
    required this.selectedIds,
    required this.orderedArticles,
    required this.expandedIds,
    required this.onToggle,
    required this.onReorder,
    required this.onToggleExpand,
  });

  final List<dynamic> library;
  final List<String> selectedIds;
  final List<Map<String, dynamic>> orderedArticles;
  final Set<String> expandedIds;
  final void Function(dynamic article) onToggle;
  final void Function(int oldIndex, int newIndex) onReorder;
  final void Function(String id) onToggleExpand;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      children: [
        const Text('Sélectionnez les clauses', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 4),
        const Text('Choisissez les clauses à inclure dans votre contrat', style: TextStyle(color: AppColors.muted, fontSize: 12.5)),
        const SizedBox(height: 14),
        if (library.isEmpty)
          const Text('Aucun article dans la bibliothèque.', style: TextStyle(color: AppColors.muted))
        else
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final article in library)
                _ClauseChip(
                  label: '${article['title'] ?? 'Article'}',
                  selected: selectedIds.contains('${article['id']}'),
                  onTap: () => onToggle(article),
                ),
            ],
          ),
        const SizedBox(height: 24),
        Text('Clauses sélectionnées (${orderedArticles.length})',
            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
        const SizedBox(height: 4),
        const Text('Réorganisez l\'ordre des clauses par glisser-déposer', style: TextStyle(color: AppColors.muted, fontSize: 12)),
        const SizedBox(height: 12),
        if (orderedArticles.isEmpty)
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.border),
              borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            ),
            child: const Center(
              child: Text('Aucune clause sélectionnée', style: TextStyle(color: AppColors.muted)),
            ),
          )
        else
          ReorderableListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: orderedArticles.length,
            onReorder: onReorder,
            itemBuilder: (context, index) {
              final article = orderedArticles[index];
              final id = '${article['id']}';
              final expanded = expandedIds.contains(id);
              return Padding(
                key: ValueKey(id),
                padding: const EdgeInsets.only(bottom: 10),
                child: PremiumCard(
                  padding: const EdgeInsets.all(0),
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        child: Row(
                          children: [
                            const Icon(Icons.drag_indicator, color: AppColors.faint, size: 18),
                            const SizedBox(width: 8),
                            CircleAvatar(
                              radius: 12,
                              backgroundColor: AppColors.primaryGold.withOpacity(0.18),
                              child: Text('${index + 1}', style: const TextStyle(color: AppColors.primaryGold, fontSize: 11, fontWeight: FontWeight.w900)),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                '${article['title'] ?? ''}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5),
                              ),
                            ),
                            IconButton(
                              onPressed: () => onToggleExpand(id),
                              icon: Icon(expanded ? Icons.expand_less : Icons.expand_more, color: AppColors.muted),
                            ),
                            IconButton(
                              onPressed: () => onToggle(article),
                              icon: const Icon(Icons.close, size: 18, color: AppColors.red),
                            ),
                          ],
                        ),
                      ),
                      if (expanded)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                          child: Align(
                            alignment: Alignment.centerLeft,
                            child: Text(
                              '${article['body'] ?? ''}',
                              style: const TextStyle(color: AppColors.muted, fontSize: 12.5, height: 1.5),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              );
            },
          ),
      ],
    );
  }
}

class _ClauseChip extends StatelessWidget {
  const _ClauseChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      avatar: selected ? const Icon(Icons.check, size: 15, color: AppColors.background) : null,
      backgroundColor: AppColors.surfaceRaised,
      selectedColor: AppColors.primaryGold,
      labelStyle: TextStyle(
        color: selected ? AppColors.background : AppColors.text,
        fontWeight: FontWeight.w800,
        fontSize: 12.5,
      ),
      side: BorderSide(color: selected ? AppColors.primaryGold : AppColors.border),
      shape: const StadiumBorder(),
    );
  }
}

// ── Step 3: Parties ──────────────────────────────────────────────────────

class _PartiesStep extends StatelessWidget {
  const _PartiesStep({
    required this.api,
    required this.clients,
    required this.search,
    required this.selectedClient,
    required this.onSelect,
    required this.onCreated,
    required this.dateDebut,
    required this.dateFin,
    required this.dateSignature,
    required this.months,
    required this.monthsController,
    required this.monthlyController,
    required this.annualController,
    required this.cautionController,
    required this.instructionController,
    required this.villeSignatureController,
    required this.modePaiement,
    required this.onDateDebutChanged,
    required this.onDateFinChanged,
    required this.onDateSignatureChanged,
    required this.onMonthsChanged,
    required this.onMonthlyChanged,
    required this.onAnnualChanged,
    required this.onModePaiementChanged,
  });

  final ApiClient api;
  final List<dynamic> clients;
  final TextEditingController search;
  final dynamic selectedClient;
  final ValueChanged<dynamic> onSelect;
  final Future<void> Function(dynamic created) onCreated;
  final DateTime dateDebut;
  final DateTime dateFin;
  final DateTime dateSignature;
  final int months;
  final TextEditingController monthsController;
  final TextEditingController monthlyController;
  final TextEditingController annualController;
  final TextEditingController cautionController;
  final TextEditingController instructionController;
  final TextEditingController villeSignatureController;
  final String modePaiement;
  final ValueChanged<DateTime> onDateDebutChanged;
  final ValueChanged<DateTime> onDateFinChanged;
  final ValueChanged<DateTime> onDateSignatureChanged;
  final ValueChanged<int> onMonthsChanged;
  final VoidCallback onMonthlyChanged;
  final VoidCallback onAnnualChanged;
  final ValueChanged<String> onModePaiementChanged;

  List<dynamic> get filtered {
    final query = search.text.trim().toLowerCase();
    if (query.isEmpty) return clients;
    return clients.where((c) => '${c['raison_sociale'] ?? ''}'.toLowerCase().contains(query)).toList();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      children: [
        const Text('Client', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 4),
        const Text('Choisissez un client existant ou créez-en un', style: TextStyle(color: AppColors.muted, fontSize: 12.5)),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: search,
                onChanged: (_) => (context as Element).markNeedsBuild(),
                decoration: const InputDecoration(hintText: 'Rechercher...', prefixIcon: Icon(Icons.search, size: 18, color: AppColors.muted)),
              ),
            ),
            const SizedBox(width: 10),
            OutlinedButton.icon(
              onPressed: () async {
                final created = await Navigator.push<dynamic>(
                  context,
                  MaterialPageRoute(builder: (_) => ClientFormScreen(api: api)),
                );
                if (created != null) await onCreated(created);
              },
              icon: const Icon(Icons.add, size: 18),
              label: const Text('Nouveau'),
            ),
          ],
        ),
        const SizedBox(height: 14),
        AnimatedBuilder(
          animation: search,
          builder: (context, _) {
            final rows = filtered;
            if (rows.isEmpty) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Text('Aucun client trouvé.', style: TextStyle(color: AppColors.muted)),
              );
            }
            return Column(
              children: [
                for (final client in rows)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: _ClientOption(
                      client: client,
                      selected: selectedClient != null && selectedClient['id'] == client['id'],
                      onTap: () => onSelect(client),
                    ),
                  ),
              ],
            );
          },
        ),
        const SizedBox(height: 26),
        const Text('Référence', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 12),
        TextField(
          controller: instructionController,
          decoration: const InputDecoration(labelText: 'N° instruction'),
          onChanged: (_) => (context as Element).markNeedsBuild(),
        ),
        const SizedBox(height: 26),
        const Text('Durée et montants', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 12),
        _DatePickerButton(label: 'Date de début', value: dateDebut, onChanged: onDateDebutChanged),
        const SizedBox(height: 12),
        _DatePickerButton(label: 'Date de fin', value: dateFin, onChanged: onDateFinChanged),
        const SizedBox(height: 12),
        TextField(
          controller: monthsController,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Durée (mois)'),
          onChanged: (v) => onMonthsChanged(int.tryParse(v) ?? months),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: monthlyController,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Redevance mensuelle (DH)'),
          onChanged: (_) => onMonthlyChanged(),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: annualController,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Redevance annuelle (DH)'),
          onChanged: (_) => onAnnualChanged(),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          value: modePaiement.isEmpty ? null : modePaiement,
          decoration: const InputDecoration(labelText: 'Mode de paiement'),
          items: const ['Virement', 'Espèces', 'Chèque', 'Carte bancaire']
              .map((mode) => DropdownMenuItem(value: mode, child: Text(mode)))
              .toList(),
          onChanged: (v) => onModePaiementChanged(v ?? ''),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: cautionController,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Caution (DH)'),
          onChanged: (_) => (context as Element).markNeedsBuild(),
        ),
        const SizedBox(height: 26),
        const Text('Signature', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 12),
        TextField(
          controller: villeSignatureController,
          decoration: const InputDecoration(labelText: 'Ville de signature *', hintText: 'Agadir'),
          onChanged: (_) => (context as Element).markNeedsBuild(),
        ),
        const SizedBox(height: 12),
        _DatePickerButton(label: 'Date de signature *', value: dateSignature, onChanged: onDateSignatureChanged),
      ],
    );
  }
}

class _DatePickerButton extends StatelessWidget {
  const _DatePickerButton({required this.label, required this.value, required this.onChanged});

  final String label;
  final DateTime value;
  final ValueChanged<DateTime> onChanged;

  String _fmt(DateTime d) => '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: value,
          firstDate: DateTime(2020),
          lastDate: DateTime(2100),
        );
        if (picked != null) onChanged(picked);
      },
      icon: const Icon(Icons.calendar_today_outlined, size: 16),
      label: Align(alignment: Alignment.centerLeft, child: Text('$label: ${_fmt(value)}')),
    );
  }
}

class _ClientOption extends StatelessWidget {
  const _ClientOption({required this.client, required this.selected, required this.onTap});

  final dynamic client;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name = '${client['raison_sociale'] ?? 'Client'}';
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: selected ? AppColors.primaryGold.withOpacity(0.10) : AppColors.surfaceRaised,
            borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            border: Border.all(color: selected ? AppColors.primaryGold : AppColors.border),
          ),
          child: Row(
            children: [
              GoldAvatar(label: name, radius: 18),
              const SizedBox(width: 10),
              Expanded(
                child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
              ),
              if (selected) const Icon(Icons.check_circle, color: AppColors.primaryGold, size: 18),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Step 4: Récapitulatif ────────────────────────────────────────────────

class _RecapStep extends StatelessWidget {
  const _RecapStep({
    required this.title,
    required this.address,
    required this.client,
    required this.articles,
    required this.dateDebut,
    required this.dateFin,
    required this.months,
    required this.monthly,
    required this.total,
    required this.caution,
    required this.instructionNo,
    required this.villeSignature,
    required this.dateSignature,
    required this.modePaiement,
  });

  final String title;
  final String address;
  final dynamic client;
  final List<Map<String, dynamic>> articles;
  final DateTime dateDebut;
  final DateTime dateFin;
  final int months;
  final double monthly;
  final double total;
  final String caution;
  final String instructionNo;
  final String villeSignature;
  final DateTime dateSignature;
  final String modePaiement;

  String _fmt(DateTime d) => '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title.isEmpty ? 'Contrat de domiciliation' : title,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 18)),
              const SizedBox(height: 4),
              Text(address, style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Client', style: TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900, fontSize: 12, letterSpacing: 1)),
              const SizedBox(height: 10),
              Row(
                children: [
                  GoldAvatar(label: '${client?['raison_sociale'] ?? '-'}', radius: 18),
                  const SizedBox(width: 10),
                  Text('${client?['raison_sociale'] ?? 'Non sélectionné'}', style: const TextStyle(fontWeight: FontWeight.w800)),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Durée et montant', style: TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900, fontSize: 12, letterSpacing: 1)),
              const SizedBox(height: 10),
              _RecapRow('Début', _fmt(dateDebut)),
              _RecapRow('Fin', _fmt(dateFin)),
              _RecapRow('Durée', '$months mois'),
              _RecapRow('Redevance mensuelle', '${monthly.toStringAsFixed(2)} DH'),
              _RecapRow('Redevance annuelle', '${total.toStringAsFixed(2)} DH'),
              if (modePaiement.isNotEmpty) _RecapRow('Mode de paiement', modePaiement),
              if (caution.isNotEmpty) _RecapRow('Caution', '$caution DH'),
              const Divider(height: 22),
              _RecapRow('Montant total', '${total.toStringAsFixed(2)} DH', strong: true),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Signature', style: TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900, fontSize: 12, letterSpacing: 1)),
              const SizedBox(height: 10),
              if (instructionNo.isNotEmpty) _RecapRow('N° instruction', instructionNo),
              _RecapRow('Ville', villeSignature.isEmpty ? '-' : villeSignature),
              _RecapRow('Date', _fmt(dateSignature)),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Clauses (${articles.length})',
                  style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900, fontSize: 12, letterSpacing: 1)),
              const SizedBox(height: 10),
              if (articles.isEmpty)
                const Text('Aucune clause sélectionnée', style: TextStyle(color: AppColors.muted))
              else
                for (final a in articles)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Text('${a['ordre']}. ${a['title'] ?? ''}', style: const TextStyle(fontSize: 13)),
                  ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RecapRow extends StatelessWidget {
  const _RecapRow(this.label, this.value, {this.strong = false});

  final String label;
  final String value;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.muted, fontSize: 13))),
          Text(
            value,
            style: TextStyle(
              fontWeight: strong ? FontWeight.w900 : FontWeight.w700,
              fontSize: strong ? 16 : 13,
              color: strong ? AppColors.primaryGold : AppColors.text,
              fontFamily: strong ? 'Fraunces' : null,
            ),
          ),
        ],
      ),
    );
  }
}

// ── Post-submit confirmation ─────────────────────────────────────────────

class _ContractCreatedView extends StatelessWidget {
  const _ContractCreatedView({required this.api, required this.contract, required this.onDone});

  final ApiClient api;
  final dynamic contract;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Contrat créé')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 84,
                height: 84,
                decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.green.withOpacity(0.12)),
                child: const Icon(Icons.check_circle_outline, color: AppColors.green, size: 42),
              ),
              const SizedBox(height: 20),
              Text('Contrat enregistré', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              const Text(
                'Le brouillon a été créé. Vous pouvez le consulter dans la liste des contrats.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.muted),
              ),
              const SizedBox(height: 24),
              PremiumButton(label: 'Terminer', onPressed: onDone),
            ],
          ),
        ),
      ),
    );
  }
}
