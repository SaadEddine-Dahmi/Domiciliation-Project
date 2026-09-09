// lib/screens/payments_screen.dart
//
// "Paiements & Facturation" — redesigned as the hub screen from the
// mockup: a 3-up stat row (Total dû / Payé / En attente), a two-panel
// layout (Contrats list on the left, selected contract's summary +
// shortcut to full detail on the right), and a "Paiements récents" list
// pulled from the currently selected contract.
//
// Data sources unchanged from the previous version:
//   GET /api/contrats                              -> contracts
//   GET /api/contrats/{id}/paiements                -> contractPayments
//   GET /api/contrats/{id}/paiements/summary         -> contractPaymentSummary

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';
import 'payment_detail_screen.dart';
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
        contracts = rows;
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
      final loadedSummary =
          await widget.api.contractPaymentSummary(contract['id']);
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
          contractTitle:
              '${selectedContract['titre_contrat'] ?? selectedContract['entreprise']?['raison_sociale'] ?? 'Contrat'}',
        ),
      ),
    );
    if (changed == true || changed == null)
      await selectContract(selectedContract);
  }

  double _sum(List<dynamic> rows, String key) => rows.fold<double>(
      0, (value, item) => value + (double.tryParse('${item[key] ?? 0}') ?? 0));

  @override
  Widget build(BuildContext context) {
    final totalDue = _sum(contracts, 'prix_total');
    final totalPaid = contracts.fold<double>(
        0, (sum, c) => sum + (double.tryParse('${c['total_paye'] ?? 0}') ?? 0));
    final totalPending = (totalDue - totalPaid).clamp(0, double.infinity);

    return Scaffold(
      appBar: AppBar(title: const Text('Paiements & Facturation')),
      body: RefreshIndicator(
        onRefresh: loadContracts,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: [
            Text.rich(
              TextSpan(
                style: Theme.of(context)
                    .textTheme
                    .displaySmall
                    ?.copyWith(fontSize: 26),
                children: const [
                  TextSpan(text: 'Paiements & '),
                  TextSpan(
                      text: 'Facturation',
                      style: TextStyle(
                          fontStyle: FontStyle.italic,
                          color: AppColors.primaryGold)),
                ],
              ),
            ),
            const SizedBox(height: 4),
            const Text('Suivi des paiements par contrat',
                style: TextStyle(color: AppColors.muted, fontSize: 12.5)),
            const SizedBox(height: 18),
            if (error != null) ...[
              ErrorBanner(message: error!),
              const SizedBox(height: 14)
            ],
            Row(
              children: [
                Expanded(
                    child: _StatBox(
                        label: 'Total dû',
                        value: totalDue,
                        icon: Icons.account_balance_wallet_outlined,
                        color: AppColors.primaryGold)),
                const SizedBox(width: 10),
                Expanded(
                    child: _StatBox(
                        label: 'Payé',
                        value: totalPaid,
                        icon: Icons.check_circle_outline,
                        color: AppColors.green)),
                const SizedBox(width: 10),
                Expanded(
                    child: _StatBox(
                        label: 'En attente',
                        value: totalPending.toDouble(),
                        icon: Icons.hourglass_top_outlined,
                        color: AppColors.amber)),
              ],
            ),
            const SizedBox(height: 18),
            if (loading)
              const Padding(
                  padding: EdgeInsets.symmetric(vertical: 40),
                  child: Center(child: CircularProgressIndicator()))
            else ...[
              PremiumCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: const [
                        Icon(Icons.description_outlined,
                            size: 16, color: AppColors.primaryGold),
                        SizedBox(width: 8),
                        Text('CONTRATS',
                            style: TextStyle(
                                color: AppColors.primaryGold,
                                fontWeight: FontWeight.w900,
                                fontSize: 12,
                                letterSpacing: 1)),
                      ],
                    ),
                    const SizedBox(height: 10),
                    if (contracts.isEmpty)
                      const Text('Aucun contrat.',
                          style: TextStyle(color: AppColors.muted))
                    else
                      for (final contract in contracts.take(8))
                        _ContractRow(
                          contract: contract,
                          selected: selectedContract?['id'] == contract['id'],
                          onTap: () => selectContract(contract),
                        ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              if (selectedContract == null)
                const PremiumCard(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Column(
                      children: [
                        Icon(Icons.folder_open_outlined,
                            color: AppColors.faint, size: 32),
                        SizedBox(height: 10),
                        Text('Sélectionnez un contrat',
                            style: TextStyle(fontWeight: FontWeight.w800)),
                        SizedBox(height: 4),
                        Text('Les paiements et factures apparaîtront ici',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                                color: AppColors.muted, fontSize: 12)),
                      ],
                    ),
                  ),
                )
              else if (loadingPayments)
                const Padding(
                    padding: EdgeInsets.symmetric(vertical: 30),
                    child: Center(child: CircularProgressIndicator()))
              else ...[
                PremiumCard(
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${selectedContract['entreprise']?['raison_sociale'] ?? selectedContract['titre_contrat'] ?? 'Contrat'}',
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                      ),
                      TextButton.icon(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (_) => PaymentDetailScreen(
                                  api: widget.api,
                                  contract: selectedContract,
                                  summary: summary,
                                  payments: payments)),
                        ),
                        icon: const Icon(Icons.open_in_new, size: 16),
                        label: const Text('Détail'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton.icon(
                      onPressed: addPayment,
                      icon: const Icon(Icons.add, size: 18),
                      label: const Text('Paiement')),
                ),
                const SizedBox(height: 14),
                const Text('PAIEMENTS RÉCENTS',
                    style: TextStyle(
                        color: AppColors.primaryGold,
                        fontWeight: FontWeight.w900,
                        fontSize: 12,
                        letterSpacing: 1)),
                const SizedBox(height: 10),
                if (payments.isEmpty)
                  const PremiumCard(
                      child: Text('Aucun paiement enregistré.',
                          style: TextStyle(color: AppColors.muted)))
                else
                  for (final payment in payments)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _PaymentRow(payment: payment),
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

class _StatBox extends StatelessWidget {
  const _StatBox(
      {required this.label,
      required this.value,
      required this.icon,
      required this.color});

  final String label;
  final double value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: const TextStyle(color: AppColors.muted, fontSize: 11.5)),
          const SizedBox(height: 6),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              '${value.toStringAsFixed(0)} DH',
              style: TextStyle(
                  fontFamily: 'Fraunces',
                  fontWeight: FontWeight.w700,
                  fontSize: 17,
                  color: color),
            ),
          ),
          const SizedBox(height: 8),
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
                color: color.withOpacity(0.14),
                borderRadius: BorderRadius.circular(9)),
            child: Icon(icon, size: 15, color: color),
          ),
        ],
      ),
    );
  }
}

class _ContractRow extends StatelessWidget {
  const _ContractRow(
      {required this.contract, required this.selected, required this.onTap});

  final dynamic contract;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
      child: Container(
        margin: const EdgeInsets.only(bottom: 6),
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
        decoration: BoxDecoration(
          color: selected
              ? AppColors.primaryGold.withOpacity(0.10)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                      '${contract['entreprise']?['raison_sociale'] ?? contract['titre_contrat'] ?? 'Contrat'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 13.5)),
                  const SizedBox(height: 3),
                  Row(
                    children: [
                      StatusPill(status: contract['statut'], compact: true),
                      const SizedBox(width: 8),
                      Text('${contract['prix_total'] ?? 0} DH',
                          style: const TextStyle(
                              color: AppColors.muted, fontSize: 12)),
                    ],
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: AppColors.faint, size: 18),
          ],
        ),
      ),
    );
  }
}

class _PaymentRow extends StatelessWidget {
  const _PaymentRow({required this.payment});

  final dynamic payment;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
                color: AppColors.green.withOpacity(0.14),
                borderRadius: BorderRadius.circular(10)),
            child:
                const Icon(Icons.north_east, size: 16, color: AppColors.green),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                    '${payment['facture']?['entreprise']?['raison_sociale'] ?? payment['mode_paiement'] ?? 'Paiement'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 13)),
                const SizedBox(height: 2),
                Text('${payment['date_paiement'] ?? ''}',
                    style: const TextStyle(
                        color: AppColors.muted, fontSize: 11.5)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('+${payment['montant'] ?? 0} DH',
                  style: const TextStyle(
                      color: AppColors.green,
                      fontWeight: FontWeight.w900,
                      fontSize: 13)),
              const SizedBox(height: 3),
              const StatusPill(status: 'paid', compact: true),
            ],
          ),
        ],
      ),
    );
  }
}
