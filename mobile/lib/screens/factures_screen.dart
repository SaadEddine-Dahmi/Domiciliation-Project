import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../widgets/api_future.dart';
import '../widgets/compact_tile.dart';
import '../widgets/error_banner.dart';

class FacturesScreen extends StatelessWidget {
  const FacturesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return ApiFuture<List<dynamic>>(
      load: () => api.listQuery('/api/factures', {'include_archived': '1'}),
      builder: (context, rows, refresh) {
        final total = sum(rows, 'montant_total');
        final paid = sum(rows, 'total_paye');
        final pending = rows.where((item) => '${item['statut'] ?? ''}' != 'paid' && item['archived_at'] == null).fold<double>(
              0,
              (value, item) => value + (double.tryParse('${item['montant_restant'] ?? item['montant_total'] ?? 0}') ?? 0),
            );
        return RefreshIndicator(
          onRefresh: refresh,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                children: [
                  Expanded(child: InvoiceStat(label: 'Total facture', value: total)),
                  const SizedBox(width: 10),
                  Expanded(child: InvoiceStat(label: 'Paye', value: paid, color: const Color(0xff22c55e))),
                  const SizedBox(width: 10),
                  Expanded(child: InvoiceStat(label: 'En attente', value: pending, color: const Color(0xfff5a623))),
                ],
              ),
              const SizedBox(height: 16),
              if (rows.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: Text('Aucune facture'),
                  ),
                )
              else
                ...rows.map(
                  (item) => CompactTile(
                    icon: Icons.receipt_long_outlined,
                    title: '${item['numero_facture'] ?? 'Facture #${item['id']}'}',
                    subtitle: '${item['entreprise']?['raison_sociale'] ?? ''} - ${item['montant_total'] ?? '0'} DH',
                    trailing: '${item['effective_statut'] ?? item['statut'] ?? ''}',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => FactureDetailScreen(api: api, facture: item)),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  double sum(List<dynamic> rows, String key) {
    return rows.fold<double>(0, (value, item) => value + (double.tryParse('${item[key] ?? 0}') ?? 0));
  }
}

class InvoiceStat extends StatelessWidget {
  const InvoiceStat({super.key, required this.label, required this.value, this.color = const Color(0xffc8a96e)});

  final String label;
  final double value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${value.toStringAsFixed(0)} DH',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: color, fontWeight: FontWeight.w900, fontSize: 18, fontFamily: 'Fraunces'),
            ),
            const SizedBox(height: 6),
            Text(label, style: const TextStyle(color: Color(0xaaf8f4ea), fontSize: 11, fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}

class FactureDetailScreen extends StatefulWidget {
  const FactureDetailScreen({super.key, required this.api, required this.facture});

  final ApiClient api;
  final dynamic facture;

  @override
  State<FactureDetailScreen> createState() => _FactureDetailScreenState();
}

class _FactureDetailScreenState extends State<FactureDetailScreen> {
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

  @override
  Widget build(BuildContext context) {
    final facture = widget.facture;
    final archived = facture['effective_statut'] == 'archived' || facture['archived_at'] != null;

    return Scaffold(
      appBar: AppBar(title: const Text('Détail facture')),
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
                    '${facture['numero_facture'] ?? 'Facture'}',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Text('${facture['entreprise']?['raison_sociale'] ?? ''}'),
                  const SizedBox(height: 8),
                  Text('Total: ${facture['montant_total'] ?? '0'} DH'),
                  Text('Payé: ${facture['total_paye'] ?? '0'} DH'),
                  Text('Restant: ${facture['montant_restant'] ?? '0'} DH'),
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
            onPressed: () => openExternal(widget.api.facturePdfUrl(facture['id'])),
            icon: const Icon(Icons.picture_as_pdf_outlined),
            label: const Text('Aperçu PDF'),
          ),
          const SizedBox(height: 10),
          FilledButton.tonalIcon(
            onPressed: () => openExternal(widget.api.facturePdfUrl(facture['id'], mode: 'download')),
            icon: const Icon(Icons.download_outlined),
            label: const Text('Telecharger PDF'),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy
                ? null
                : () => run(
                      () => archived
                          ? widget.api.restoreFacture(facture['id'])
                          : widget.api.archiveFacture(facture['id']),
                    ),
            icon: Icon(archived ? Icons.unarchive_outlined : Icons.archive_outlined),
            label: Text(archived ? 'Restaurer' : 'Archiver'),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy
                ? null
                : () async {
                    final ok = await showDialog<bool>(
                      context: context,
                      builder: (_) => AlertDialog(
                        title: const Text('Supprimer la facture ?'),
                        content: const Text('Cette action supprime aussi le paiement associé.'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
                          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
                        ],
                      ),
                    );
                    if (ok == true) await run(() => widget.api.deleteFacture(facture['id']));
                  },
            icon: const Icon(Icons.delete_outline),
            label: const Text('Supprimer'),
          ),
        ],
      ),
    );
  }
}
