import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';
import 'payment_form_screen.dart';

class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends State<PaymentsScreen> {
  List<dynamic> contracts = [];
  List<dynamic> payments = [];
  Map<String, dynamic>? summary;
  dynamic selectedContract;
  bool loading = true;
  bool loadingPayments = false;
  String? error;

  @override
  void initState() {
    super.initState();
    loadContracts();
  }

  Future<void> loadContracts() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final rows = await widget.api.list('/api/contrats');
      if (!mounted) return;
      setState(() {
        contracts = rows.where((item) => ['active', 'draft'].contains('${item['statut'] ?? ''}')).toList();
        loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loading = false;
      });
    }
  }

  Future<void> selectContract(dynamic contract) async {
    setState(() {
      selectedContract = contract;
      loadingPayments = true;
      payments = [];
      summary = null;
      error = null;
    });
    try {
      final loadedPayments = await widget.api.contractPayments(contract['id']);
      final loadedSummary = await widget.api.contractPaymentSummary(contract['id']);
      if (!mounted) return;
      setState(() {
        payments = loadedPayments;
        summary = loadedSummary;
        loadingPayments = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loadingPayments = false;
      });
    }
  }

  Future<void> addPayment() async {
    if (selectedContract == null) return;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => PaymentFormScreen(
          api: widget.api,
          contractId: selectedContract['id'],
          contractTitle: '${selectedContract['entreprise']?['raison_sociale'] ?? selectedContract['titre_contrat'] ?? 'Contrat'}',
        ),
      ),
    );
    if (changed == true || changed == null) await selectContract(selectedContract);
  }

  @override
  Widget build(BuildContext context) {
    final total = double.tryParse('${summary?['prix_total'] ?? 0}') ?? 0;
    final paid = double.tryParse('${summary?['total_paye'] ?? 0}') ?? 0;
    final remaining = double.tryParse('${summary?['restant'] ?? 0}') ?? 0;
    final progress = total <= 0 ? 0.0 : (paid / total).clamp(0.0, 1.0).toDouble();

    return Scaffold(
      appBar: AppBar(title: const Text('Paiements & Facturation')),
      body: RefreshIndicator(
        onRefresh: selectedContract == null ? loadContracts : () => selectContract(selectedContract),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text('Paiements & Facturation', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 30)),
            const SizedBox(height: 6),
            const Text('Suivi des paiements par contrat.', style: TextStyle(color: AppColors.muted)),
            const SizedBox(height: 18),
            if (error != null) ...[
              ErrorBanner(message: error!),
              const SizedBox(height: 12),
            ],
            if (loading)
              const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()))
            else ...[
              PremiumCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Contrats', style: TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 10),
                    if (contracts.isEmpty)
                      const Text('Aucun contrat actif.', style: TextStyle(color: AppColors.muted))
                    else
                      for (final contract in contracts.take(8))
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text('${contract['entreprise']?['raison_sociale'] ?? contract['titre_contrat'] ?? 'Contrat'}'),
                          subtitle: Text('${contract['prix_total'] ?? 0} DH'),
                          trailing: StatusPill(status: contract['statut'], compact: true),
                          selected: selectedContract?['id'] == contract['id'],
                          onTap: () => selectContract(contract),
                        ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              if (selectedContract == null)
                const PremiumCard(child: Text('Selectionnez un contrat pour voir ses paiements.', textAlign: TextAlign.center))
              else if (loadingPayments)
                const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()))
              else ...[
                PremiumCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text('${selectedContract['entreprise']?['raison_sociale'] ?? 'Contrat'}', style: const TextStyle(fontWeight: FontWeight.w900))),
                          FilledButton.icon(onPressed: remaining <= 0 ? null : addPayment, icon: const Icon(Icons.add), label: const Text('Paiement')),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(child: _AmountBox(label: 'Total contrat', value: total, color: AppColors.primaryGold)),
                          const SizedBox(width: 8),
                          Expanded(child: _AmountBox(label: 'Paye', value: paid, color: AppColors.green)),
                          const SizedBox(width: 8),
                          Expanded(child: _AmountBox(label: 'Restant', value: remaining, color: AppColors.red)),
                        ],
                      ),
                      const SizedBox(height: 12),
                      LinearProgressIndicator(value: progress, color: AppColors.primaryGold, backgroundColor: AppColors.surfaceRaised),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                PremiumCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Historique des paiements', style: TextStyle(fontWeight: FontWeight.w900)),
                      const SizedBox(height: 10),
                      if (payments.isEmpty)
                        const Text('Aucun paiement enregistre.', style: TextStyle(color: AppColors.muted))
                      else
                        for (final payment in payments)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.payments_outlined, color: AppColors.green),
                            title: Text('+${payment['montant'] ?? 0} DH', style: const TextStyle(color: AppColors.green, fontWeight: FontWeight.w900)),
                            subtitle: Text('${payment['mode_paiement'] ?? ''}'),
                            trailing: Text('${payment['date_paiement'] ?? ''}', style: const TextStyle(color: AppColors.muted)),
                          ),
                    ],
                  ),
                ),
              ],
            ],
            const SizedBox(height: 80),
          ],
        ),
      ),
    );
  }
}

class _AmountBox extends StatelessWidget {
  const _AmountBox({required this.label, required this.value, required this.color});

  final String label;
  final double value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AppColors.surfaceRaised, borderRadius: BorderRadius.circular(14)),
      child: Column(
        children: [
          Text(label, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.muted, fontSize: 11)),
          const SizedBox(height: 6),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text('${value.toStringAsFixed(0)} DH', style: TextStyle(color: color, fontFamily: 'Fraunces', fontWeight: FontWeight.w900)),
          ),
        ],
      ),
    );
  }
}
