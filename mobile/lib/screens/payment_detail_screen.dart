// lib/screens/payment_detail_screen.dart
//
// "Détail des paiements" — new screen matching image 6 (right panel):
// contract header with status pill, a Total contrat / Payé / Restant
// 3-column summary, a progress bar, then "Historique des paiements" rows
// with mode-specific icons, and a "Télécharger l'historique" action.
//
// Data is passed in from PaymentsScreen (already fetched there) rather
// than re-fetched, to avoid a duplicate round-trip when navigating from
// the hub's "Détail" button.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../theme/app_design.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';

class PaymentDetailScreen extends StatelessWidget {
  const PaymentDetailScreen({
    super.key,
    required this.api,
    required this.contract,
    required this.summary,
    required this.payments,
  });

  final ApiClient api;
  final dynamic contract;
  final Map<String, dynamic>? summary;
  final List<dynamic> payments;

  IconData _iconForMode(String mode) {
    final m = mode.toLowerCase();
    if (m.contains('virement')) return Icons.account_balance_outlined;
    if (m.contains('chèque') || m.contains('cheque'))
      return Icons.receipt_long_outlined;
    if (m.contains('espèce') || m.contains('espece'))
      return Icons.payments_outlined;
    if (m.contains('carte')) return Icons.credit_card_outlined;
    return Icons.payments_outlined;
  }

  @override
  Widget build(BuildContext context) {
    final total = double.tryParse('${summary?['prix_total'] ?? 0}') ?? 0;
    final paid = double.tryParse('${summary?['total_paye'] ?? 0}') ?? 0;
    final remaining = double.tryParse('${summary?['restant'] ?? 0}') ?? 0;
    final progress =
        total <= 0 ? 0.0 : (paid / total).clamp(0.0, 1.0).toDouble();

    return Scaffold(
      appBar: AppBar(title: const Text('Détail des paiements')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.page),
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${contract['titre_contrat'] ?? 'Contrat de domiciliation'}',
                        style: const TextStyle(
                            fontWeight: FontWeight.w900, fontSize: 15),
                      ),
                    ),
                    StatusPill(status: contract['statut']),
                  ],
                ),
                const SizedBox(height: 4),
                Text('CT-${contract['id']}',
                    style: const TextStyle(
                        color: AppColors.primaryGold,
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                        fontFamily: 'monospace')),
                Text('${contract['entreprise']?['raison_sociale'] ?? ''}',
                    style: const TextStyle(
                        color: AppColors.muted, fontSize: 12.5)),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                        child: _AmountColumn(
                            label: 'Total contrat',
                            value: total,
                            color: AppColors.text)),
                    Expanded(
                        child: _AmountColumn(
                            label: 'Payé',
                            value: paid,
                            color: AppColors.green)),
                    Expanded(
                        child: _AmountColumn(
                            label: 'Restant',
                            value: remaining,
                            color: AppColors.amber)),
                  ],
                ),
                const SizedBox(height: 14),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 8,
                      backgroundColor: AppColors.surfaceRaised,
                      color: AppColors.primaryGold),
                ),
                const SizedBox(height: 6),
                Align(
                  alignment: Alignment.centerRight,
                  child: Text('${(progress * 100).toStringAsFixed(1)}%',
                      style: const TextStyle(
                          color: AppColors.muted, fontSize: 11)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          const Text('Historique des paiements',
              style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
          const SizedBox(height: 10),
          if (payments.isEmpty)
            const PremiumCard(
                child: Text('Aucun paiement enregistré.',
                    style: TextStyle(color: AppColors.muted)))
          else
            for (final payment in payments)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: PremiumCard(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('+${payment['montant'] ?? 0} DH',
                                style: const TextStyle(
                                    color: AppColors.green,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 14)),
                            const SizedBox(height: 2),
                            Text('${payment['mode_paiement'] ?? ''}',
                                style: const TextStyle(
                                    color: AppColors.muted, fontSize: 12)),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text('${payment['date_paiement'] ?? ''}',
                              style: const TextStyle(
                                  color: AppColors.muted, fontSize: 11.5)),
                          const SizedBox(height: 3),
                          Text(
                              'Réf. ${payment['facture']?['numero_facture'] ?? payment['id']}',
                              style: const TextStyle(
                                  color: AppColors.faint, fontSize: 10.5)),
                        ],
                      ),
                      const SizedBox(width: 6),
                      const Icon(Icons.chevron_right,
                          color: AppColors.faint, size: 18),
                    ],
                  ),
                ),
              ),
          const SizedBox(height: 18),
          PremiumButton(
            outlined: true,
            icon: Icons.download_outlined,
            label: "Télécharger l'historique",
            onPressed: () {
              // TODO(api_client): wire to a real export endpoint if/when
              // one exists (e.g. GET /api/contrats/{id}/paiements/export).
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                    content:
                        Text("Export de l'historique — à connecter à l'API.")),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _AmountColumn extends StatelessWidget {
  const _AmountColumn(
      {required this.label, required this.value, required this.color});

  final String label;
  final double value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(color: AppColors.muted, fontSize: 11)),
        const SizedBox(height: 3),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text('${value.toStringAsFixed(0)} DH',
              style: TextStyle(
                  fontFamily: 'Fraunces',
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                  color: color)),
        ),
      ],
    );
  }
}
