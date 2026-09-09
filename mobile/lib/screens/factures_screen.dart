// lib/screens/factures_screen.dart
//
// Factures — redesigned to match the mockup: 3-up stat row (Total facturé
// / Payé / En attente), search + period filter, and a list of invoice
// rows with a colored status pill (Payée / En attente / En retard).
//
// "En retard" is derived client-side: statut === 'pending' AND
// date_facture is in the past — the backend's Facture enum only has
// pending/paid/cancelled, so overdue is a display-layer concept, not a
// stored status.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';
import 'facture_detail_screen.dart';

class FacturesScreen extends StatefulWidget {
  const FacturesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<FacturesScreen> createState() => _FacturesScreenState();
}

class _FacturesScreenState extends State<FacturesScreen> {
  List<dynamic> factures = [];
  bool loading = true;
  String? error;
  final search = TextEditingController();
  String period = 'Toutes';

  @override
  void initState() {
    super.initState();
    search.addListener(() => setState(() {}));
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final rows = await widget.api.list('/api/factures');
      if (!mounted) return;
      setState(() {
        factures = rows;
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

  bool _isOverdue(dynamic f) {
    if ('${f['statut'] ?? ''}' != 'pending') return false;
    final date = DateTime.tryParse('${f['date_facture'] ?? ''}');
    if (date == null) return false;
    return date.isBefore(DateTime.now().subtract(const Duration(days: 15)));
  }

  String _statusKey(dynamic f) {
    if (_isOverdue(f)) return 'en retard';
    return '${f['statut'] ?? 'pending'}';
  }

  List<dynamic> get filtered {
    var rows = factures;
    final query = search.text.trim().toLowerCase();
    if (query.isNotEmpty) {
      rows = rows.where((f) {
        final numero = '${f['numero_facture'] ?? ''}'.toLowerCase();
        final client =
            '${f['entreprise']?['raison_sociale'] ?? ''}'.toLowerCase();
        return numero.contains(query) || client.contains(query);
      }).toList();
    }
    return rows;
  }

  double _sum(String key) => factures.fold<double>(
      0, (s, f) => s + (double.tryParse('${f[key] ?? 0}') ?? 0));

  @override
  Widget build(BuildContext context) {
    final totalFacture = _sum('montant_total');
    final totalPaid = factures.where((f) => f['statut'] == 'paid').fold<double>(
        0, (s, f) => s + (double.tryParse('${f['montant_total'] ?? 0}') ?? 0));
    final totalPending = totalFacture - totalPaid;

    return Scaffold(
      appBar: AppBar(title: const Text('Factures')),
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
                  Row(
                    children: [
                      Expanded(
                          child: _StatChip(
                              label: 'Total facturé',
                              value: totalFacture,
                              color: AppColors.primaryGold)),
                      const SizedBox(width: 10),
                      Expanded(
                          child: _StatChip(
                              label: 'Payé',
                              value: totalPaid,
                              color: AppColors.green)),
                      const SizedBox(width: 10),
                      Expanded(
                          child: _StatChip(
                              label: 'En attente',
                              value: totalPending,
                              color: AppColors.amber)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: search,
                          decoration: const InputDecoration(
                              hintText: 'Rechercher une facture...',
                              prefixIcon: Icon(Icons.search,
                                  size: 18, color: AppColors.muted)),
                        ),
                      ),
                      const SizedBox(width: 10),
                      DropdownButton<String>(
                        value: period,
                        underline: const SizedBox.shrink(),
                        dropdownColor: AppColors.surfaceRaised,
                        items: const [
                          'Toutes',
                          'Ce mois',
                          'Ce trimestre',
                          'Cette année'
                        ]
                            .map((p) => DropdownMenuItem(
                                value: p,
                                child: Text(p,
                                    style: const TextStyle(fontSize: 12))))
                            .toList(),
                        onChanged: (v) =>
                            setState(() => period = v ?? 'Toutes'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (filtered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 60),
                      child: Center(
                          child: Text('Aucune facture',
                              style: TextStyle(color: AppColors.muted))),
                    )
                  else
                    for (final facture in filtered)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _FactureRow(
                          facture: facture,
                          statusKey: _statusKey(facture),
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                                builder: (_) => FactureDetailScreen(
                                    api: widget.api, facture: facture)),
                          ),
                          onDownload: () => openExternal(widget.api
                              .facturePdfUrl(facture['id'], mode: 'download')),
                        ),
                      ),
                  const SizedBox(height: 80),
                ],
              ),
            ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip(
      {required this.label, required this.value, required this.color});

  final String label;
  final double value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
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
          const SizedBox(height: 4),
          Text(label,
              style: const TextStyle(color: AppColors.muted, fontSize: 10.5)),
        ],
      ),
    );
  }
}

class _FactureRow extends StatelessWidget {
  const _FactureRow(
      {required this.facture,
      required this.statusKey,
      required this.onTap,
      required this.onDownload});

  final dynamic facture;
  final String statusKey;
  final VoidCallback onTap;
  final VoidCallback onDownload;

  static const _labels = {
    'paid': 'Payée',
    'pending': 'En attente',
    'en retard': 'En retard',
    'cancelled': 'Annulée'
  };
  static const _colors = {
    'paid': AppColors.green,
    'pending': AppColors.amber,
    'en retard': AppColors.red,
    'cancelled': AppColors.faint
  };

  @override
  Widget build(BuildContext context) {
    final color = _colors[statusKey] ?? AppColors.faint;
    final label = _labels[statusKey] ?? statusKey;

    return PremiumCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      onTap: onTap,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                        '${facture['numero_facture'] ?? 'FAC-${facture['id']}'}',
                        style: const TextStyle(
                            color: AppColors.primaryGold,
                            fontWeight: FontWeight.w900,
                            fontSize: 12.5,
                            fontFamily: 'monospace')),
                  ],
                ),
                const SizedBox(height: 3),
                Text('${facture['entreprise']?['raison_sociale'] ?? '-'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 13.5)),
                const SizedBox(height: 2),
                Text('${facture['date_facture'] ?? ''}',
                    style: const TextStyle(
                        color: AppColors.muted, fontSize: 11.5)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('${facture['montant_total'] ?? 0} DH',
                  style: const TextStyle(
                      fontWeight: FontWeight.w900, fontSize: 13.5)),
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                    color: color.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: color.withOpacity(0.35))),
                child: Text(label,
                    style: TextStyle(
                        color: color,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800)),
              ),
            ],
          ),
          IconButton(
              onPressed: onDownload,
              icon: const Icon(Icons.download_outlined,
                  size: 18, color: AppColors.muted)),
        ],
      ),
    );
  }
}
