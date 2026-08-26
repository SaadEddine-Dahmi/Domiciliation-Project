import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/api_future.dart';
import '../widgets/compact_tile.dart';
import '../widgets/error_banner.dart';
import '../widgets/gold_avatar.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';
import 'client_form_screen.dart';
import 'contract_form_screen.dart';
import 'contracts_screen.dart';
import 'send_message_screen.dart';

class ClientsScreen extends StatefulWidget {
  const ClientsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ClientsScreen> createState() => _ClientsScreenState();
}

class _ClientsScreenState extends State<ClientsScreen> {
  String search = '';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ApiFuture<List<dynamic>>(
        load: () => widget.api.list('/api/clients'),
        builder: (context, items, refresh) {
          final q = search.trim().toLowerCase();
          final filtered = q.isEmpty
              ? items
              : items.where((item) {
                  final haystack = [
                    item['raison_sociale'],
                    item['representant']?['nom'],
                    item['representant']?['prenom'],
                    item['representant']?['cin'],
                  ].join(' ').toLowerCase();
                  return haystack.contains(q);
                }).toList();
          return RefreshIndicator(
            onRefresh: refresh,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                TextField(
                  onChanged: (value) => setState(() => search = value),
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.search),
                    labelText: 'Rechercher un client...',
                  ),
                ),
                const SizedBox(height: 12),
                Text('${filtered.length} clients', style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w800)),
                const SizedBox(height: 16),
                if (filtered.isEmpty)
                  const PremiumCard(child: Text('Aucun client'))
                else
                  ...filtered.map(
                    (item) => ClientListTile(
                      item: item,
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => ClientDetailScreen(api: widget.api, client: item)),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: const Color(0xffc8a96e),
        foregroundColor: const Color(0xff13161f),
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => ClientFormScreen(api: widget.api)),
        ),
        child: const Icon(Icons.add),
      ),
    );
  }
}

class ClientListTile extends StatelessWidget {
  const ClientListTile({super.key, required this.item, required this.onTap});

  final dynamic item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final title = '${item['raison_sociale'] ?? 'Client'}';
    return PremiumCard(
      margin: const EdgeInsets.only(bottom: 10),
      onTap: onTap,
      child: Row(
        children: [
          GoldAvatar(label: title),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(
                  '${item['ville'] ?? item['representant']?['adresse'] ?? ''}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
              ],
            ),
          ),
          StatusPill(status: item['statut'] ?? 'actif', compact: true),
          const Icon(Icons.chevron_right, color: AppColors.muted),
        ],
      ),
    );
  }
}

class CompactStatus extends StatelessWidget {
  const CompactStatus({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return StatusPill(status: label, compact: true);
  }
}

class ClientDetailScreen extends StatefulWidget {
  const ClientDetailScreen({super.key, required this.api, required this.client});

  final ApiClient api;
  final dynamic client;

  @override
  State<ClientDetailScreen> createState() => _ClientDetailScreenState();
}

class _ClientDetailScreenState extends State<ClientDetailScreen> {
  late dynamic client = widget.client;
  List<dynamic> contracts = [];
  List<dynamic> documents = [];
  List<dynamic> documentTypes = [];
  String? error;
  bool busy = false;
  bool loadingRelations = true;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    try {
      final fresh = await widget.api.getClient(client['id']);
      final loadedContracts = await widget.api.listQuery('/api/contrats', {'entreprise_id': '${client['id']}'});
      final loadedDocuments = await widget.api.listQuery('/api/documents', {'entreprise_id': '${client['id']}'});
      final loadedTypes = await widget.api.list('/api/document-types');
      if (!mounted) return;
      setState(() {
        client = fresh;
        contracts = loadedContracts;
        documents = loadedDocuments;
        documentTypes = loadedTypes;
        loadingRelations = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loadingRelations = false;
      });
    }
  }

  Future<void> run(Future<void> Function() action) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action();
      await refresh();
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Object? get clientUserId => client['client_user_id'] ?? client['clientUser']?['id'] ?? client['client_user']?['id'];

  Future<void> uploadDocument() async {
    if (documentTypes.isEmpty) {
      setState(() => error = 'Aucun type de document disponible.');
      return;
    }

    Object? selectedType = documentTypes.first['id'];
    final expiration = TextEditingController();
    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(context).viewInsets.bottom + 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Ajouter un document', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 16),
              DropdownButtonFormField<Object>(
                value: selectedType,
                decoration: const InputDecoration(labelText: 'Type de document'),
                items: documentTypes
                    .map((type) => DropdownMenuItem<Object>(
                          value: type['id'],
                          child: Text('${type['name'] ?? 'Document'}'),
                        ))
                    .toList(),
                onChanged: (value) => setModalState(() => selectedType = value),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: expiration,
                decoration: const InputDecoration(labelText: 'Date expiration YYYY-MM-DD'),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context, true),
                icon: const Icon(Icons.upload_file_outlined),
                label: const Text('Choisir le fichier'),
              ),
            ],
          ),
        ),
      ),
    );
    if (confirmed != true || selectedType == null) return;
    final expirationText = expiration.text;
    expiration.dispose();

    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
      withData: true,
    );
    final file = result?.files.single;
    if (file == null) return;

    await run(() async {
      await widget.api.uploadDocument(
        entrepriseId: client['id'],
        documentTypeId: selectedType!,
        file: file,
        dateExpiration: expirationText,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final isActive = client['statut'] == 'actif';
    final contact = client['clientUser'] ?? client['client_user'];
    final representant = client['representant'];

    return Scaffold(
      appBar: AppBar(title: const Text('Detail client')),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${client['raison_sociale'] ?? 'Client'}',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Text('${client['forme_juridique'] ?? ''} - ${client['ville'] ?? ''}'),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        Chip(label: Text(isActive ? 'Actif' : 'Inactif')),
                        if (contact?['email'] != null) Chip(label: Text('${contact['email']}')),
                        if (representant?['telephone'] != null) Chip(label: Text('${representant['telephone']}')),
                        if (representant?['cin'] != null) Chip(label: Text('CIN ${representant['cin']}')),
                      ],
                    ),
                    if (representant != null) ...[
                      const SizedBox(height: 12),
                      Text('Representant: ${representant['prenom'] ?? ''} ${representant['nom'] ?? ''}'.trim()),
                      if (representant['adresse'] != null) Text('Residence: ${representant['adresse']}'),
                    ],
                  ],
                ),
              ),
            ),
            if (error != null) ...[
              const SizedBox(height: 12),
              ErrorBanner(message: error!),
            ],
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                FilledButton.icon(
                  onPressed: busy
                      ? null
                      : () async {
                          final updated = await Navigator.push<dynamic>(
                            context,
                            MaterialPageRoute(builder: (_) => ClientFormScreen(api: widget.api, client: client)),
                          );
                          if (updated != null) setState(() => client = updated);
                        },
                  icon: const Icon(Icons.edit_outlined),
                  label: const Text('Modifier'),
                ),
                OutlinedButton.icon(
                  onPressed: busy
                      ? null
                      : () => run(() async {
                            final next = isActive ? 'inactif' : 'actif';
                            await widget.api.setClientStatus(client['id'], next);
                          }),
                  icon: Icon(isActive ? Icons.block : Icons.check_circle_outline),
                  label: Text(isActive ? 'Suspendre' : 'Reactiver'),
                ),
                OutlinedButton.icon(
                  onPressed: busy
                      ? null
                      : () => run(() async {
                            final password = await widget.api.resetClientPassword(client['id']);
                            if (mounted && password != null) {
                              await showDialog<void>(
                                context: context,
                                builder: (_) => AlertDialog(
                                  title: const Text('Nouveau mot de passe'),
                                  content: SelectableText(password),
                                  actions: [
                                    TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer')),
                                  ],
                                ),
                              );
                            }
                          }),
                  icon: const Icon(Icons.key_outlined),
                  label: const Text('Mot de passe'),
                ),
                FilledButton.tonalIcon(
                  onPressed: clientUserId == null
                      ? null
                      : () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => SendMessageScreen(
                                api: widget.api,
                                clientUserId: clientUserId!,
                                clientName: '${client['raison_sociale'] ?? 'Client'}',
                              ),
                            ),
                          ),
                  icon: const Icon(Icons.mail_outline),
                  label: const Text('Message'),
                ),
              ],
            ),
            const SizedBox(height: 18),
            SectionHeader(
              icon: Icons.description_outlined,
              title: 'Contrats',
              actionLabel: 'Ajouter',
              onAction: busy
                  ? null
                  : () async {
                      final created = await Navigator.push<dynamic>(
                        context,
                        MaterialPageRoute(builder: (_) => ContractFormScreen(api: widget.api, initialClient: client)),
                      );
                      if (created != null) refresh();
                    },
            ),
            if (loadingRelations)
              const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()))
            else if (contracts.isEmpty)
              const EmptyPanel(label: 'Aucun contrat pour ce client.')
            else
              ...contracts.map(
                (contract) => CompactTile(
                  icon: Icons.description_outlined,
                  title: '${contract['titre_contrat'] ?? 'Contrat'}',
                  subtitle: '${contract['date_debut'] ?? '-'} - ${contract['date_fin'] ?? '-'}',
                  trailing: '${contract['statut'] ?? ''}',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => ContractDetailScreen(api: widget.api, contract: contract)),
                  ).then((_) => refresh()),
                ),
              ),
            const SizedBox(height: 18),
            SectionHeader(
              icon: Icons.folder_outlined,
              title: 'Documents',
              actionLabel: 'Ajouter',
              onAction: busy ? null : uploadDocument,
            ),
            if (!loadingRelations && documents.isEmpty)
              const EmptyPanel(label: 'Aucun document importe.')
            else
              ...documents.map(
                (document) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: const Icon(Icons.insert_drive_file_outlined),
                    title: Text('${document['name'] ?? 'Document'}'),
                    subtitle: Text('Expiration: ${document['date_expiration'] ?? '-'}'),
                    onTap: () => openExternal(widget.api.documentPreviewUrl(document['id'])),
                    trailing: PopupMenuButton<String>(
                      onSelected: (value) {
                        if (value == 'preview') openExternal(widget.api.documentPreviewUrl(document['id']));
                        if (value == 'download') openExternal(widget.api.documentDownloadUrl(document['id']));
                        if (value == 'delete') run(() => widget.api.deleteDocument(document['id']));
                      },
                      itemBuilder: (_) => const [
                        PopupMenuItem(value: 'preview', child: Text('Apercu')),
                        PopupMenuItem(value: 'download', child: Text('Telecharger')),
                        PopupMenuItem(value: 'delete', child: Text('Supprimer')),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.icon,
    required this.title,
    required this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 8),
          Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
          TextButton.icon(
            onPressed: onAction,
            icon: const Icon(Icons.add),
            label: Text(actionLabel),
          ),
        ],
      ),
    );
  }
}

class EmptyPanel extends StatelessWidget {
  const EmptyPanel({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Text(label),
      ),
    );
  }
}
