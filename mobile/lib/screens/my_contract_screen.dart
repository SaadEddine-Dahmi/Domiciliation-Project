// lib/screens/my_contract_screen.dart
//
// "Mon contrat" — client-role screen matching image 7 (right panel):
// a status badge, contract title, start/end dates, the total amount in
// large serif type, a document illustration with a checkmark badge, a
// confirmation message, and a "Télécharger le PDF" button.
//
// Data source: GET /api/dashboard/stats (client branch of
// DashboardController::stats()), which returns:
//   { entreprise, domiciliataire, contrat: { id, statut, date_debut,
//     date_fin, prix_total, pdf_path, pdf_url }, role: 'client' }
//
// pdf_url is only present when the contract has a generated PDF; when
// null, the download button is disabled with an explanatory note instead
// of a broken link.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';

class MyContractScreen extends StatefulWidget {
  const MyContractScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<MyContractScreen> createState() => _MyContractScreenState();
}

class _MyContractScreenState extends State<MyContractScreen> {
  Map<String, dynamic>? contrat;
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
      final stats = await widget.api.dashboardStats();
      if (!mounted) return;
      setState(() {
        contrat = stats['contrat'] is Map
            ? Map<String, dynamic>.from(stats['contrat'] as Map)
            : null;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mon contrat')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.page),
                children: [
                  if (error != null) ...[
                    ErrorBanner(message: error!),
                    const SizedBox(height: 14)
                  ],
                  if (contrat == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 80),
                      child: Center(
                        child: Column(
                          children: [
                            Icon(Icons.description_outlined,
                                size: 44, color: AppColors.faint),
                            SizedBox(height: 12),
                            Text('Aucun contrat disponible pour le moment.',
                                style: TextStyle(color: AppColors.muted)),
                          ],
                        ),
                      ),
                    )
                  else
                    _ContractCard(api: widget.api, contrat: contrat!),
                ],
              ),
            ),
    );
  }
}

class _ContractCard extends StatelessWidget {
  const _ContractCard({required this.api, required this.contrat});

  final ApiClient api;
  final Map<String, dynamic> contrat;

  bool get isActive => '${contrat['statut'] ?? ''}' == 'active';
  String? get pdfUrl => contrat['pdf_url'] as String?;

  @override
  Widget build(BuildContext context) {
    final total = contrat['prix_total'];

    return PremiumCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: (isActive ? AppColors.green : AppColors.amber)
                  .withOpacity(0.12),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                  color: (isActive ? AppColors.green : AppColors.amber)
                      .withOpacity(0.4)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: isActive ? AppColors.green : AppColors.amber)),
                const SizedBox(width: 7),
                Text(
                  isActive
                      ? 'CONTRAT ACTIF'
                      : '${contrat['statut'] ?? '-'}'.toUpperCase(),
                  style: TextStyle(
                      color: isActive ? AppColors.green : AppColors.amber,
                      fontWeight: FontWeight.w900,
                      fontSize: 11,
                      letterSpacing: 0.6),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          Text(
            '${contrat['titre_contrat'] ?? 'Contrat de domiciliation'}',
            textAlign: TextAlign.center,
            style: const TextStyle(
                fontFamily: 'Fraunces',
                fontSize: 21,
                fontWeight: FontWeight.w700,
                color: AppColors.text),
          ),
          const SizedBox(height: 10),
          const _GoldDivider(),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                  child: _DateColumn(
                      label: 'Date de début',
                      value: '${contrat['date_debut'] ?? '-'}')),
              Container(width: 1, height: 34, color: AppColors.border),
              Expanded(
                  child: _DateColumn(
                      label: 'Date de fin',
                      value: '${contrat['date_fin'] ?? '-'}')),
            ],
          ),
          const Divider(height: 32, color: AppColors.border),
          const Text('Montant total du contrat',
              style: TextStyle(color: AppColors.muted, fontSize: 12)),
          const SizedBox(height: 6),
          Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: total != null
                      ? '${double.tryParse('$total')?.toStringAsFixed(2) ?? total} '
                      : '- ',
                  style: const TextStyle(
                      fontFamily: 'Fraunces',
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primaryGold),
                ),
                const TextSpan(
                    text: 'DH',
                    style: TextStyle(
                        fontFamily: 'Fraunces',
                        fontSize: 16,
                        color: AppColors.primaryGold)),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Container(
            width: 92,
            height: 92,
            alignment: Alignment.center,
            decoration: BoxDecoration(
                color: AppColors.surfaceRaised,
                borderRadius: BorderRadius.circular(18)),
            child: Stack(
              alignment: Alignment.center,
              children: [
                const Icon(Icons.description_outlined,
                    size: 40, color: AppColors.muted),
                Positioned(
                  right: 10,
                  bottom: 10,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                        shape: BoxShape.circle, color: AppColors.primaryGold),
                    child: const Icon(Icons.check,
                        size: 15, color: AppColors.background),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            isActive
                ? 'Votre contrat est en vigueur.'
                : 'Statut du contrat : ${contrat['statut'] ?? '-'}',
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
          ),
          if (isActive) ...[
            const SizedBox(height: 2),
            const Text('Merci pour votre confiance.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.muted, fontSize: 12.5)),
          ],
          const SizedBox(height: 22),
          PremiumButton(
            icon: Icons.download_outlined,
            label: 'Télécharger le PDF',
            onPressed: pdfUrl == null ? null : () => openExternal(pdfUrl!),
          ),
          if (pdfUrl == null) ...[
            const SizedBox(height: 8),
            const Text(
              'Le PDF sera disponible dès que votre domiciliataire aura généré le contrat.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.faint, fontSize: 11.5),
            ),
          ],
        ],
      ),
    );
  }
}

class _GoldDivider extends StatelessWidget {
  const _GoldDivider();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: const [
        SizedBox(
            width: 30,
            child: Divider(color: AppColors.primaryGold, thickness: 1)),
        Padding(
            padding: EdgeInsets.symmetric(horizontal: 8),
            child: Icon(Icons.circle, size: 4, color: AppColors.primaryGold)),
        SizedBox(
            width: 30,
            child: Divider(color: AppColors.primaryGold, thickness: 1)),
      ],
    );
  }
}

class _DateColumn extends StatelessWidget {
  const _DateColumn({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.calendar_today_outlined,
                size: 13, color: AppColors.muted)
          ],
        ),
        const SizedBox(height: 6),
        Text(label,
            style: const TextStyle(color: AppColors.muted, fontSize: 11)),
        const SizedBox(height: 3),
        Text(value,
            style:
                const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
      ],
    );
  }
}
