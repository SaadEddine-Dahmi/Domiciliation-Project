// lib/screens/facture_detail_screen.dart
//
// Facture detail — PDF preview action + archive/restore/delete controls,
// carried over from the previous FactureDetailScreen (now split into its
// own file) and restyled to the shared theme.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_card.dart';

class FactureDetailScreen extends StatefulWidget {
  const FactureDetailScreen(
      {super.key, required this.api, required this.facture});

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
    final archived = facture['effective_statut'] == 'archived' ||
        facture['archived_at'] != null;

    return Scaffold(
      appBar: AppBar(title: const Text('Détail facture')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.page),
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${facture['numero_facture'] ?? 'Facture'}',
                    style: Theme.of(context)
                        .textTheme
                        .titleLarge
                        ?.copyWith(fontSize: 18)),
                const SizedBox(height: 8),
                Text('${facture['entreprise']?['raison_sociale'] ?? ''}',
                    style: const TextStyle(color: AppColors.muted)),
                const Divider(height: 24),
                _Row('Total', '${facture['montant_total'] ?? '0'} DH'),
                _Row('Payé', '${facture['total_paye'] ?? '0'} DH',
                    color: AppColors.green),
                _Row('Restant', '${facture['montant_restant'] ?? '0'} DH',
                    color: AppColors.red),
              ],
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            ErrorBanner(message: error!)
          ],
          const SizedBox(height: 16),
          PremiumButton(
            onPressed: () =>
                openExternal(widget.api.facturePdfUrl(facture['id'])),
            icon: Icons.picture_as_pdf_outlined,
            label: 'Aperçu PDF',
          ),
          const SizedBox(height: 10),
          PremiumButton(
            outlined: true,
            onPressed: () => openExternal(
                widget.api.facturePdfUrl(facture['id'], mode: 'download')),
            icon: Icons.download_outlined,
            label: 'Télécharger PDF',
          ),
          const SizedBox(height: 10),
          PremiumButton(
            outlined: true,
            onPressed: busy
                ? null
                : () => run(() => archived
                    ? widget.api.restoreFacture(facture['id'])
                    : widget.api.archiveFacture(facture['id'])),
            icon: archived ? Icons.unarchive_outlined : Icons.archive_outlined,
            label: archived ? 'Restaurer' : 'Archiver',
          ),
          const SizedBox(height: 10),
          PremiumButton(
            outlined: true,
            onPressed: busy
                ? null
                : () async {
                    final ok = await showDialog<bool>(
                      context: context,
                      builder: (_) => AlertDialog(
                        title: const Text('Supprimer la facture ?'),
                        content: const Text(
                            'Cette action supprime aussi le paiement associé.'),
                        actions: [
                          TextButton(
                              onPressed: () => Navigator.pop(context, false),
                              child: const Text('Annuler')),
                          FilledButton(
                              onPressed: () => Navigator.pop(context, true),
                              child: const Text('Supprimer')),
                        ],
                      ),
                    );
                    if (ok == true)
                      await run(() => widget.api.deleteFacture(facture['id']));
                  },
            icon: Icons.delete_outline,
            label: 'Supprimer',
          ),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value, {this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
              child:
                  Text(label, style: const TextStyle(color: AppColors.muted))),
          Text(value,
              style: TextStyle(
                  fontWeight: FontWeight.w800, color: color ?? AppColors.text)),
        ],
      ),
    );
  }
}
