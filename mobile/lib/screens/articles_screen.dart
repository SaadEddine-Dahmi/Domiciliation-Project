import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/api_future.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';

class ArticlesScreen extends StatefulWidget {
  const ArticlesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ArticlesScreen> createState() => _ArticlesScreenState();
}

class _ArticlesScreenState extends State<ArticlesScreen> {
  String search = '';
  int listVersion = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Articles & Clauses')),
      body: ApiFuture<List<dynamic>>(
        key: ValueKey(listVersion),
        load: () => widget.api.list('/api/articles'),
        builder: (context, articles, refresh) {
          final filtered = articles.where((article) {
            final q = search.trim().toLowerCase();
            if (q.isEmpty) return true;
            return '${article['title'] ?? ''} ${article['body'] ?? ''}'.toLowerCase().contains(q);
          }).toList();
          final active = articles.where((article) => article['is_active'] == true).length;

          return RefreshIndicator(
            onRefresh: refresh,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text('Articles', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 30)),
                const SizedBox(height: 6),
                const Text('Bibliotheque des clauses reutilisables dans les contrats.', style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(child: _MiniStat(label: 'Total articles', value: '${articles.length}', color: AppColors.primaryGold)),
                    const SizedBox(width: 10),
                    Expanded(child: _MiniStat(label: 'Actifs', value: '$active', color: AppColors.green)),
                    const SizedBox(width: 10),
                    Expanded(child: _MiniStat(label: 'Inactifs', value: '${articles.length - active}', color: AppColors.muted)),
                  ],
                ),
                const SizedBox(height: 14),
                TextField(
                  onChanged: (value) => setState(() => search = value),
                  decoration: const InputDecoration(
                    labelText: 'Rechercher par titre ou contenu...',
                    prefixIcon: Icon(Icons.search),
                  ),
                ),
                const SizedBox(height: 16),
                if (filtered.isEmpty)
                  const PremiumCard(child: Text('Aucun article dans la bibliotheque.'))
                else
                  ...filtered.map(
                    (article) => _ArticleTile(
                      article: article,
                      onEdit: () => openEditor(article: article, refresh: refresh),
                      onToggle: () => toggleActive(article, refresh),
                      onDelete: () => deleteArticle(article, refresh),
                    ),
                  ),
                const SizedBox(height: 96),
              ],
            ),
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppColors.primaryGold,
        foregroundColor: AppColors.background,
        onPressed: () => openEditor(),
        child: const Icon(Icons.add),
      ),
    );
  }

  Future<void> openEditor({dynamic article, Future<void> Function()? refresh}) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => ArticleEditorScreen(api: widget.api, article: article)),
    );
    if (changed == true && refresh != null) {
      await refresh();
    } else if (changed == true && mounted) {
      setState(() => listVersion++);
    }
  }

  Future<void> toggleActive(dynamic article, Future<void> Function() refresh) async {
    try {
      await widget.api.updateArticle(article['id'], {
        'title': article['title'] ?? '',
        'body': article['body'] ?? '',
        'is_active': article['is_active'] != true,
      });
      await refresh();
    } catch (e) {
      showError(e);
    }
  }

  Future<void> deleteArticle(dynamic article, Future<void> Function() refresh) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Supprimer l article ?'),
        content: const Text('Cette action est definitive si l article n est pas utilise par un contrat ou un modele.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await widget.api.deleteArticle(article['id']);
      await refresh();
    } catch (e) {
      showError(e);
    }
  }

  void showError(Object e) {
    final message = e is ApiException ? e.message : e.toString();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class ArticleEditorScreen extends StatefulWidget {
  const ArticleEditorScreen({super.key, required this.api, this.article});

  final ApiClient api;
  final dynamic article;

  @override
  State<ArticleEditorScreen> createState() => _ArticleEditorScreenState();
}

class _ArticleEditorScreenState extends State<ArticleEditorScreen> {
  final title = TextEditingController();
  final body = TextEditingController();
  final formKey = GlobalKey<FormState>();
  bool isActive = true;
  bool saving = false;
  String? error;

  bool get editing => widget.article != null;

  @override
  void initState() {
    super.initState();
    title.text = '${widget.article?['title'] ?? ''}';
    body.text = '${widget.article?['body'] ?? ''}';
    isActive = widget.article?['is_active'] ?? true;
    body.addListener(refreshCounter);
  }

  @override
  void dispose() {
    body.removeListener(refreshCounter);
    title.dispose();
    body.dispose();
    super.dispose();
  }

  void refreshCounter() {
    if (mounted) setState(() {});
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    setState(() {
      saving = true;
      error = null;
    });

    try {
      final payload = {
        'title': title.text.trim(),
        'body': body.text.trim(),
        'is_active': isActive,
      };
      if (editing) {
        await widget.api.updateArticle(widget.article['id'], payload);
      } else {
        await widget.api.createArticle(payload);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  void insertVariable(String key) {
    final tag = '{{${key}}}';
    final selection = body.selection;
    final text = body.text;
    final start = selection.isValid ? selection.start : text.length;
    final end = selection.isValid ? selection.end : text.length;
    body.text = text.replaceRange(start, end, tag);
    body.selection = TextSelection.collapsed(offset: start + tag.length);
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Modifier l article' : 'Nouvel article')),
      body: Form(
        key: formKey,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (error != null) ...[
              ErrorBanner(message: error!),
              const SizedBox(height: 14),
            ],
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Contenu de l article', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: title,
                    decoration: const InputDecoration(labelText: 'Titre *', prefixIcon: Icon(Icons.title)),
                    validator: (value) => value == null || value.trim().isEmpty ? 'Le titre est obligatoire.' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: body,
                    minLines: 9,
                    maxLines: 16,
                    decoration: const InputDecoration(
                      alignLabelWithHint: true,
                      labelText: 'Corps de l article *',
                      prefixIcon: Icon(Icons.subject),
                    ),
                    validator: (value) => value == null || value.trim().isEmpty ? 'Le contenu est obligatoire.' : null,
                  ),
                  const SizedBox(height: 8),
                  Text('${body.text.length} caracteres', style: const TextStyle(color: AppColors.soft, fontSize: 12)),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Article actif'),
                    subtitle: const Text('Disponible dans le wizard de creation de contrat.'),
                    value: isActive,
                    onChanged: (value) => setState(() => isActive = value),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            PremiumCard(child: VariablePicker(onInsert: insertVariable)),
            const SizedBox(height: 96),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: FilledButton.icon(
            onPressed: saving ? null : save,
            icon: saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.save_outlined),
            label: Text(saving ? 'Sauvegarde...' : editing ? 'Mettre a jour' : 'Creer l article'),
          ),
        ),
      ),
    );
  }
}

class VariablePicker extends StatelessWidget {
  const VariablePicker({super.key, required this.onInsert});

  final void Function(String key) onInsert;

  static const groups = [
    VariableGroup('Domiciliataire', [
      ArticleVariable('domiciliataire_nom', 'Nom'),
      ArticleVariable('domiciliataire_rc', 'RC'),
      ArticleVariable('domiciliataire_if', 'IF'),
    ]),
    VariableGroup('Entreprise', [
      ArticleVariable('raison_sociale', 'Raison sociale'),
      ArticleVariable('forme_juridique', 'Forme juridique'),
      ArticleVariable('adresse_entreprise', 'Adresse'),
      ArticleVariable('ville', 'Ville'),
      ArticleVariable('pays', 'Pays'),
      ArticleVariable('capital', 'Capital'),
    ]),
    VariableGroup('Representant', [
      ArticleVariable('gerant_nom', 'Nom complet'),
      ArticleVariable('gerant_cin', 'CIN'),
      ArticleVariable('gerant_adresse', 'Adresse'),
      ArticleVariable('gerant_telephone', 'Telephone'),
      ArticleVariable('gerant_email', 'Email'),
      ArticleVariable('gerant_nationalite', 'Nationalite'),
    ]),
    VariableGroup('Contrat', [
      ArticleVariable('instruction_no', 'N Instruction'),
      ArticleVariable('date_debut', 'Date debut'),
      ArticleVariable('date_fin', 'Date fin'),
      ArticleVariable('duree_mois', 'Duree mois'),
      ArticleVariable('date_signature', 'Date signature'),
      ArticleVariable('ville_signature', 'Ville signature'),
    ]),
    VariableGroup('Financier', [
      ArticleVariable('redevance_mensuelle', 'Redevance mensuelle'),
      ArticleVariable('redevance_annuelle', 'Redevance annuelle'),
      ArticleVariable('caution', 'Caution'),
      ArticleVariable('mode_paiement', 'Mode paiement'),
    ]),
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: const [
            Expanded(child: Text('Variables disponibles', style: TextStyle(fontWeight: FontWeight.w900))),
            Text('Touchez pour inserer', style: TextStyle(color: AppColors.soft, fontSize: 12)),
          ],
        ),
        const SizedBox(height: 14),
        for (final group in groups) ...[
          Text(group.name, style: const TextStyle(color: AppColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final variable in group.variables)
                ActionChip(
                  avatar: const Icon(Icons.code, size: 16, color: AppColors.primaryGold),
                  label: Text(variable.label),
                  tooltip: '{{${variable.key}}}',
                  onPressed: () => onInsert(variable.key),
                  backgroundColor: AppColors.primaryGold.withOpacity(0.10),
                  side: BorderSide(color: AppColors.primaryGold.withOpacity(0.18)),
                  labelStyle: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w800),
                ),
            ],
          ),
          const SizedBox(height: 14),
        ],
        const Text(
          'Exemple: {{raison_sociale}} sera remplace dans le PDF par la valeur du client selectionne.',
          style: TextStyle(color: AppColors.soft, fontSize: 12),
        ),
      ],
    );
  }
}

class VariableGroup {
  const VariableGroup(this.name, this.variables);

  final String name;
  final List<ArticleVariable> variables;
}

class ArticleVariable {
  const ArticleVariable(this.key, this.label);

  final String key;
  final String label;
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value, required this.color});

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        children: [
          Text(value, style: TextStyle(color: color, fontFamily: 'Fraunces', fontWeight: FontWeight.w900, fontSize: 24)),
          const SizedBox(height: 4),
          Text(label, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.muted, fontSize: 11)),
        ],
      ),
    );
  }
}

class _ArticleTile extends StatelessWidget {
  const _ArticleTile({
    required this.article,
    required this.onEdit,
    required this.onToggle,
    required this.onDelete,
  });

  final dynamic article;
  final VoidCallback onEdit;
  final VoidCallback onToggle;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final active = article['is_active'] == true;
    return PremiumCard(
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text('${article['title'] ?? 'Article'}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16))),
              StatusPill(status: active ? 'actif' : 'inactif', compact: true),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '${article['body'] ?? ''}'.isEmpty ? 'Aucun contenu' : '${article['body']}',
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: AppColors.muted, height: 1.45),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              OutlinedButton.icon(onPressed: onToggle, icon: Icon(active ? Icons.pause_circle_outline : Icons.play_circle_outline), label: Text(active ? 'Desactiver' : 'Activer')),
              const SizedBox(width: 8),
              Expanded(child: OutlinedButton.icon(onPressed: onEdit, icon: const Icon(Icons.edit_outlined), label: const Text('Modifier'))),
              IconButton(onPressed: onDelete, icon: const Icon(Icons.delete_outline, color: AppColors.red), tooltip: 'Supprimer'),
            ],
          ),
        ],
      ),
    );
  }
}
