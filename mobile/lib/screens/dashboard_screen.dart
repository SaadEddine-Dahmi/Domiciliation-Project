import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/app_user.dart';
import '../models/stat_item.dart';
import '../theme/app_design.dart';
import '../widgets/api_future.dart';
import '../widgets/compact_tile.dart';
import '../widgets/gold_avatar.dart';
import '../widgets/premium_card.dart';
import '../widgets/stat_card.dart';
import '../widgets/status_pill.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    super.key,
    required this.api,
    required this.user,
    required this.onOpenSection,
  });

  final ApiClient api;
  final AppUser user;
  final void Function(String section) onOpenSection;

  @override
  Widget build(BuildContext context) {
    return ApiFuture<Map<String, dynamic>>(
      load: api.dashboardStats,
      builder: (context, stats, refresh) {
        final entries = user.isClient
            ? clientStats(stats)
            : [
                StatItem('Clients', stats['total_clients'] ?? 0, Icons.people_outline, onTap: user.isDomiciliataire ? () => onOpenSection('clients') : null),
                if (user.isAdmin) StatItem('Domiciliataires', stats['total_domiciliataires'] ?? 0, Icons.admin_panel_settings_outlined, onTap: () => onOpenSection('domiciliataires')),
                if (!user.isAdmin) ...[
                  StatItem('Contrats actifs', stats['contrats_actifs'] ?? 0, Icons.verified_outlined, onTap: () => onOpenSection('contracts')),
                  StatItem('Brouillons', stats['contrats_draft'] ?? 0, Icons.edit_outlined, onTap: () => onOpenSection('contracts')),
                  StatItem('CA du mois', '${stats['ca_mensuel'] ?? '0'} DH', Icons.show_chart, onTap: () => onOpenSection('factures')),
                ],
              ];

        return RefreshIndicator(
          onRefresh: refresh,
          child: ListView(
            padding: EdgeInsets.all(isDesktop(context) ? 28 : 20),
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Bonjour, ${user.name.split(' ').first}',
                          style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: isDesktop(context) ? 40 : 30),
                        ),
                        const SizedBox(height: 8),
                        Text(user.roleLabel, style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w800)),
                      ],
                    ),
                  ),
                  if (isDesktop(context)) IconButton(tooltip: 'Actualiser', onPressed: refresh, icon: const Icon(Icons.refresh_rounded, color: AppColors.primaryGold)),
                ],
              ),
              const SizedBox(height: 22),
              SizedBox(
                height: 148,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: entries.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                  itemBuilder: (context, i) => SizedBox(width: 166, child: StatCard(item: entries[i])),
                ),
              ),
              if (user.isDomiciliataire) ...[
                const SizedBox(height: 18),
                _QuickActions(onOpenSection: onOpenSection),
                const SizedBox(height: 18),
                _RecentBusiness(api: api, onOpenSection: onOpenSection),
              ],
              if (user.isClient && stats['timeline'] is List) ...[
                const SizedBox(height: 20),
                _PanelTitle(title: 'Activite recente', onTap: refresh, actionLabel: 'Actualiser'),
                ...List<dynamic>.from(stats['timeline'] as List).map(
                  (item) => CompactTile(
                    icon: Icons.history,
                    title: '${item['title'] ?? 'Activite'}',
                    subtitle: '${item['description'] ?? ''}',
                    trailing: '${item['date'] ?? ''}',
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  List<StatItem> clientStats(Map<String, dynamic> stats) {
    final entreprise = stats['entreprise'] as Map<String, dynamic>?;
    final contrat = stats['contrat'] as Map<String, dynamic>?;
    return [
      StatItem('Entreprise', entreprise?['raison_sociale'] ?? 'Aucune', Icons.business_outlined),
      StatItem('Contrat', contrat?['statut'] ?? 'Aucun', Icons.description_outlined, onTap: () => onOpenSection('contracts')),
      StatItem('Fin', contrat?['date_fin'] ?? '-', Icons.event_outlined),
      StatItem('Montant', contrat?['prix_total'] != null ? '${contrat?['prix_total']} DH' : '-', Icons.payments_outlined),
    ];
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.onOpenSection});

  final void Function(String section) onOpenSection;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        _ActionChipButton(icon: Icons.add_business, label: 'Creer client', onTap: () => onOpenSection('new_client')),
        _ActionChipButton(icon: Icons.note_add_outlined, label: 'Creer contrat', onTap: () => onOpenSection('new_contract')),
        _ActionChipButton(icon: Icons.receipt_long_outlined, label: 'Factures', onTap: () => onOpenSection('factures')),
        _ActionChipButton(icon: Icons.mail_outline, label: 'Messages', onTap: () => onOpenSection('messages')),
      ],
    );
  }
}

class _ActionChipButton extends StatelessWidget {
  const _ActionChipButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ActionChip(
      avatar: Icon(icon, size: 18, color: AppColors.primaryGold),
      label: Text(label),
      onPressed: onTap,
      backgroundColor: AppColors.surfaceRaised,
      side: const BorderSide(color: AppColors.border),
      labelStyle: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w800),
    );
  }
}

class _RecentBusiness extends StatelessWidget {
  const _RecentBusiness({required this.api, required this.onOpenSection});

  final ApiClient api;
  final void Function(String section) onOpenSection;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final clients = _RecentList(
          title: 'Derniers clients',
          actionLabel: 'Voir tout',
          onTap: () => onOpenSection('clients'),
          load: () => api.list('/api/clients'),
          rowBuilder: (item) => _ClientPreviewRow(client: item),
        );
        final contracts = _RecentList(
          title: 'Derniers contrats',
          actionLabel: 'Voir tout',
          onTap: () => onOpenSection('contracts'),
          load: () => api.list('/api/contrats'),
          rowBuilder: (item) => _ContractPreviewRow(contract: item),
        );

        if (constraints.maxWidth < 720) {
          return Column(children: [clients, const SizedBox(height: 14), contracts]);
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [Expanded(child: clients), const SizedBox(width: 14), Expanded(child: contracts)],
        );
      },
    );
  }
}

class _RecentList extends StatelessWidget {
  const _RecentList({required this.title, required this.actionLabel, required this.onTap, required this.load, required this.rowBuilder});

  final String title;
  final String actionLabel;
  final VoidCallback onTap;
  final Future<List<dynamic>> Function() load;
  final Widget Function(dynamic item) rowBuilder;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        children: [
          _PanelTitle(title: title, actionLabel: actionLabel, onTap: onTap),
          const SizedBox(height: 8),
          FutureBuilder<List<dynamic>>(
            future: load(),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Padding(padding: EdgeInsets.all(18), child: Center(child: CircularProgressIndicator()));
              }
              final rows = (snapshot.data ?? const []).take(4).toList();
              if (rows.isEmpty) {
                return const Padding(padding: EdgeInsets.all(16), child: Text('Aucune donnee pour le moment.', style: TextStyle(color: AppColors.muted)));
              }
              return Column(children: rows.map(rowBuilder).toList());
            },
          ),
        ],
      ),
    );
  }
}

class _PanelTitle extends StatelessWidget {
  const _PanelTitle({required this.title, this.actionLabel, this.onTap});

  final String title;
  final String? actionLabel;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w900))),
        if (actionLabel != null) TextButton(onPressed: onTap, child: Text(actionLabel!)),
      ],
    );
  }
}

class _ClientPreviewRow extends StatelessWidget {
  const _ClientPreviewRow({required this.client});

  final dynamic client;

  @override
  Widget build(BuildContext context) {
    final name = '${client['raison_sociale'] ?? client['name'] ?? 'Client'}';
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: GoldAvatar(label: name, radius: 22),
      title: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900)),
      subtitle: Text('${client['ville'] ?? client['city'] ?? client['adresse'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: StatusPill(status: client['statut'] ?? client['status'] ?? 'actif', compact: true),
    );
  }
}

class _ContractPreviewRow extends StatelessWidget {
  const _ContractPreviewRow({required this.contract});

  final dynamic contract;

  @override
  Widget build(BuildContext context) {
    final ref = '${contract['reference'] ?? contract['numero'] ?? contract['titre_contrat'] ?? 'Contrat'}';
    final client = contract['entreprise'] is Map ? contract['entreprise']['raison_sociale'] : contract['client'];
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(ref, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900)),
      subtitle: Text('${client ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: StatusPill(status: contract['statut'] ?? contract['status'] ?? 'brouillon', compact: true),
    );
  }
}
