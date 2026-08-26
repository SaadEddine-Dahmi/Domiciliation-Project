import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';

class TemplatesScreen extends StatefulWidget {
  const TemplatesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TemplatesScreen> createState() => _TemplatesScreenState();
}

class _TemplatesScreenState extends State<TemplatesScreen> {
  List<dynamic> templates = [];
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
      final loaded = await widget.api.list('/api/templates');
      if (mounted) {
        setState(() {
          templates = loaded;
          loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error = e is ApiException ? e.message : e.toString();
          loading = false;
        });
      }
    }
  }

  Future<void> openEditor({dynamic template}) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => TemplateEditorScreen(api: widget.api, template: template)),
    );
    if (changed == true) await load();
  }

  Future<void> remove(dynamic template) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Supprimer le modele ?'),
        content: const Text('Ce modele ne sera plus disponible dans la creation de contrats.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.api.deleteTemplate(template['id']);
      await load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e is ApiException ? e.message : e.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Templates')),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text('Templates', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 30)),
            const SizedBox(height: 6),
            const Text('Modeles reutilisables pour accelerer la creation de contrats.', style: TextStyle(color: AppColors.muted)),
            const SizedBox(height: 18),
            if (loading)
              const Center(child: Padding(padding: EdgeInsets.all(28), child: CircularProgressIndicator()))
            else if (error != null)
              ErrorBanner(message: error!)
            else if (templates.isEmpty)
              PremiumCard(
                child: Column(
                  children: [
                    const Icon(Icons.dashboard, color: AppColors.primaryGold, size: 42),
                    const SizedBox(height: 12),
                    const Text('Aucun template cree.', style: TextStyle(fontWeight: FontWeight.w900)),
                    const SizedBox(height: 12),
                    FilledButton.icon(onPressed: () => openEditor(), icon: const Icon(Icons.add), label: const Text('Creer un template')),
                  ],
                ),
              )
            else
              ...templates.map(
                (template) => PremiumCard(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text('${template['name'] ?? 'Template'}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16))),
                          Text('${(template['articles'] as List?)?.length ?? 0} art.', style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w800)),
                        ],
                      ),
                      if ('${template['description'] ?? ''}'.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text('${template['description']}', style: const TextStyle(color: AppColors.muted)),
                      ],
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(child: OutlinedButton.icon(onPressed: () => openEditor(template: template), icon: const Icon(Icons.edit), label: const Text('Modifier'))),
                          const SizedBox(width: 8),
                          IconButton(onPressed: () => remove(template), icon: const Icon(Icons.delete_outline, color: AppColors.red)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 80),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppColors.primaryGold,
        foregroundColor: AppColors.background,
        onPressed: () => openEditor(),
        child: const Icon(Icons.add),
      ),
    );
  }
}

class TemplateEditorScreen extends StatefulWidget {
  const TemplateEditorScreen({super.key, required this.api, this.template});

  final ApiClient api;
  final dynamic template;

  @override
  State<TemplateEditorScreen> createState() => _TemplateEditorScreenState();
}

class _TemplateEditorScreenState extends State<TemplateEditorScreen> {
  final formKey = GlobalKey<FormState>();
  final name = TextEditingController();
  final description = TextEditingController();
  List<dynamic> articles = [];
  final selected = <Object, dynamic>{};
  bool loading = true;
  bool saving = false;
  String? error;

  bool get editing => widget.template != null;

  @override
  void initState() {
    super.initState();
    name.text = '${widget.template?['name'] ?? ''}';
    description.text = '${widget.template?['description'] ?? ''}';
    for (final article in List<dynamic>.from(widget.template?['articles'] as List? ?? const [])) {
      selected[article['id']] = article;
    }
    loadArticles();
  }

  @override
  void dispose() {
    name.dispose();
    description.dispose();
    super.dispose();
  }

  Future<void> loadArticles() async {
    try {
      final loaded = await widget.api.list('/api/articles');
      if (mounted) {
        setState(() {
          articles = loaded;
          loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error = e is ApiException ? e.message : e.toString();
          loading = false;
        });
      }
    }
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    setState(() {
      saving = true;
      error = null;
    });
    final payload = {
      'name': name.text.trim(),
      'description': description.text.trim(),
      'articles': selected.values
          .toList()
          .asMap()
          .entries
          .map((entry) => {'id': '${entry.value['id']}', 'ordre': entry.key + 1})
          .toList(),
    };

    try {
      if (editing) {
        await widget.api.updateTemplate(widget.template['id'], payload);
      } else {
        await widget.api.createTemplate(payload);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selectedRows = selected.values.toList();
    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Modifier template' : 'Nouveau template')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: formKey,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  if (error != null) ...[
                    ErrorBanner(message: error!),
                    const SizedBox(height: 12),
                  ],
                  PremiumCard(
                    child: Column(
                      children: [
                        TextFormField(
                          controller: name,
                          decoration: const InputDecoration(labelText: 'Nom du template *'),
                          validator: (value) => value == null || value.trim().isEmpty ? 'Champ obligatoire' : null,
                        ),
                        const SizedBox(height: 12),
                        TextField(controller: description, decoration: const InputDecoration(labelText: 'Description')),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  PremiumCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Articles', style: Theme.of(context).textTheme.titleLarge),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final article in articles)
                              FilterChip(
                                selected: selected.containsKey(article['id']),
                                label: Text('${article['title'] ?? 'Article'}'),
                                onSelected: (checked) {
                                  setState(() {
                                    if (checked) {
                                      selected[article['id']] = article;
                                    } else {
                                      selected.remove(article['id']);
                                    }
                                  });
                                },
                              ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Text('Ordre (${selectedRows.length})', style: const TextStyle(fontWeight: FontWeight.w900)),
                        ReorderableListView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: selectedRows.length,
                          onReorder: (oldIndex, newIndex) {
                            if (newIndex > oldIndex) newIndex -= 1;
                            final rows = selected.entries.toList();
                            final moved = rows.removeAt(oldIndex);
                            rows.insert(newIndex, moved);
                            setState(() {
                              selected
                                ..clear()
                                ..addEntries(rows);
                            });
                          },
                          itemBuilder: (context, index) {
                            final article = selectedRows[index];
                            return ListTile(
                              key: ValueKey(article['id']),
                              leading: CircleAvatar(backgroundColor: AppColors.primaryGold, child: Text('${index + 1}', style: const TextStyle(color: AppColors.background))),
                              title: Text('${article['title'] ?? 'Article'}'),
                              trailing: IconButton(icon: const Icon(Icons.delete_outline, color: AppColors.red), onPressed: () => setState(() => selected.remove(article['id']))),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 96),
                ],
              ),
            ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: FilledButton.icon(
            onPressed: saving ? null : save,
            icon: saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.save),
            label: Text(saving ? 'Sauvegarde...' : editing ? 'Mettre a jour' : 'Creer template'),
          ),
        ),
      ),
    );
  }
}
