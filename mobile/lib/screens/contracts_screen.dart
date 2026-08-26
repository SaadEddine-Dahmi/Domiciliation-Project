import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../widgets/compact_tile.dart';
import '../widgets/error_banner.dart';
import '../widgets/resource_list.dart';
import 'contract_form_screen.dart';
import 'payment_form_screen.dart';

class ContractsScreen extends StatelessWidget {
  const ContractsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ResourceList(
        load: () => api.list('/api/contrats'),
        emptyTitle: 'Aucun contrat',
        itemBuilder: (item) {
          final company = item['entreprise']?['raison_sociale'];
          final title = item['titre_contrat'] ?? company ?? 'Contrat #${item['id']}';
          return CompactTile(
            icon: Icons.description_outlined,
            title: '$title',
            subtitle: '${item['date_debut'] ?? '-'} - ${item['date_fin'] ?? '-'}',
            trailing: '${item['statut'] ?? ''}',
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => ContractDetailScreen(api: api, contract: item)),
            ),
          );
        },
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => ContractFormScreen(api: api)),
        ),
        icon: const Icon(Icons.note_add_outlined),
        label: const Text('Contrat'),
      ),
    );
  }
}

class ContractDetailScreen extends StatefulWidget {
  const ContractDetailScreen({super.key, required this.api, required this.contract});

  final ApiClient api;
  final dynamic contract;

  @override
  State<ContractDetailScreen> createState() => _ContractDetailScreenState();
}

class _ContractDetailScreenState extends State<ContractDetailScreen> {
  String? error;
  bool busy = false;

  Future<void> run(Future<void> Function() action) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> uploadSignedPdf() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf'],
      withData: true,
    );
    final file = result?.files.single;
    if (file == null) return;
    await run(() => widget.api.activateContract(widget.contract['id'], file));
  }

  void showContractPreview() {
    final contract = widget.contract;
    final articles = List<dynamic>.from(contract['articles'] as List? ?? const []);
    showDialog<void>(
      context: context,
      builder: (_) => Dialog(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520, maxHeight: 720),
          child: ListView(
            padding: const EdgeInsets.all(22),
            children: [
              Text('${contract['titre_contrat'] ?? 'Contrat de domiciliation'}', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
              Text('${contract['entreprise']?['raison_sociale'] ?? 'Client'}'),
              const Divider(height: 28),
              Text('Periode: ${contract['date_debut'] ?? '-'} - ${contract['date_fin'] ?? '-'}'),
              Text('Montant: ${contract['prix_total'] ?? 0} DH'),
              Text('Statut: ${contract['statut'] ?? '-'}'),
              const SizedBox(height: 18),
              Text('Articles (${articles.length})', style: const TextStyle(fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              if (articles.isEmpty)
                const Text('Aucun article associe.')
              else
                for (var i = 0; i < articles.length; i++)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text('Article ${i + 1} - ${articles[i]['title'] ?? articles[i]['titre'] ?? 'Article'}'),
                    subtitle: Text('${articles[i]['body'] ?? ''}', maxLines: 4, overflow: TextOverflow.ellipsis),
                  ),
              const SizedBox(height: 12),
              FilledButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer')),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final contract = widget.contract;
    final company = contract['entreprise']?['raison_sociale'] ?? 'Client';
    final status = contract['statut']?.toString() ?? '';
    final isDraft = status == 'draft';

    return Scaffold(
      appBar: AppBar(title: const Text('Détail contrat')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${contract['titre_contrat'] ?? 'Contrat'}',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Text('$company • $status'),
                  const SizedBox(height: 8),
                  Text('${contract['date_debut'] ?? '-'} → ${contract['date_fin'] ?? '-'}'),
                  if (contract['prix_total'] != null) Text('${contract['prix_total']} DH'),
                ],
              ),
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            ErrorBanner(message: error!),
          ],
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: showContractPreview,
            icon: const Icon(Icons.visibility_outlined),
            label: const Text('Apercu dans l app'),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy ? null : () => openExternal(widget.api.contractPdfUrl(contract['id'])),
            icon: const Icon(Icons.download_outlined),
            label: const Text('Telecharger PDF'),
          ),
          const SizedBox(height: 10),
          FilledButton.tonalIcon(
            onPressed: busy
                ? null
                : () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => PaymentFormScreen(
                          api: widget.api,
                          contractId: contract['id'],
                          contractTitle: '${contract['titre_contrat'] ?? 'Contrat'}',
                        ),
                      ),
                    ),
            icon: const Icon(Icons.receipt_long_outlined),
            label: const Text('Créer facture / paiement'),
          ),
          const SizedBox(height: 10),
          FilledButton.tonalIcon(
            onPressed: busy ? null : uploadSignedPdf,
            icon: const Icon(Icons.upload_file_outlined),
            label: const Text('Importer contrat légalisé'),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy ? null : () => run(() => widget.api.renewContract(contract['id'])),
            icon: const Icon(Icons.autorenew),
            label: const Text('Renouveler'),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy ? null : () => run(() => widget.api.terminateContract(contract['id'])),
            icon: const Icon(Icons.cancel_outlined),
            label: const Text('Résilier'),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy ? null : () => run(() => widget.api.archiveContract(contract['id'])),
            icon: const Icon(Icons.archive_outlined),
            label: const Text('Archiver'),
          ),
          if (isDraft) ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: busy
                  ? null
                  : () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (_) => AlertDialog(
                          title: const Text('Supprimer le brouillon ?'),
                          content: const Text('Cette action supprime définitivement le brouillon.'),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
                            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
                          ],
                        ),
                      );
                      if (ok == true) await run(() => widget.api.deleteContract(contract['id']));
                    },
              icon: const Icon(Icons.delete_outline),
              label: const Text('Supprimer brouillon'),
            ),
          ],
        ],
      ),
    );
  }
}
