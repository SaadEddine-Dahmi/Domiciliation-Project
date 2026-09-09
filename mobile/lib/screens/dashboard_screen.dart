// lib/screens/dashboard_screen.dart
//
// Dashboard — redesigned to match the mockup: greeting header with wave
// emoji, notification bell with unread badge, a 2x2/horizontal stat grid,
// then "Derniers clients" and "Derniers contrats" panels.
//
// Role handling is unchanged from the previous version:
//   - admin           -> Clients / Domiciliataires stats
//   - domiciliataire  -> Clients / Contrats actifs / Brouillons / CA du mois
//                        + quick actions + recent business panels
//   - client          -> Entreprise / Contrat / Fin / Montant stat cards
//                        + activity timeline if present in stats payload

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
    this.unreadNotifications = 0,
  });

  final ApiClient api;
  final AppUser user;
  final void Function(String section) onOpenSection;
  final int unreadNotifications;

  @override
  Widget build(BuildContext context) {
    return ApiFuture<Map<String, dynamic>>(
      load: api.dashboardStats,
      builder: (context, stats, refresh) {
        final entries = user.isClient
            ? clientStats(stats)
            : [
                StatItem('Clients', stats['total_clients'] ?? 0, Icons.people_outline,
                    onTap: user.isDomiciliataire ? () => onOpenSection('clients') : null),
                if (user.isAdmin)
                  StatItem('Domiciliataires', stats['total_domiciliataires'] ?? 0, Icons.admin_panel_settings_outlined,
                      onTap: () => onOpenSection('domiciliataires')),
                if (!user.isAdmin) ...[
                  StatItem('Contrats actifs', stats['contrats_actifs'] ?? 0, Icons.verified_outlined,
                      onTap: () => onOpenSection('contracts')),
                  StatItem('Brouillons', stats['contrats_draft'] ?? 0, Icons.edit_outlined,
                      onTap: () => onOpenSection('contracts')),
                  StatItem('CA du mois', '${stats['ca_mensuel'] ?? '0'} DH', Icons.show_chart,
                      onTap: () => onOpenSection('factures')),
                ],
              ];

        return RefreshIndicator(
          onRefresh: refresh,
          child: ListView(
            padding: EdgeInsets.all(isDesktop(context) ? 28 : 20),
            children: [
              _DashboardHeader(
                user: user,
                unreadNotifications: unreadNotifications,
                onBellTap: () => onOpenSection('notifications'),
              ),
              const SizedBox(height: 22),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: isDesktop(context) ? 4 : 2,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.15,
                children: [for (final entry in entries) StatCard(item: entry)],
              ),
              if (user.isDomiciliataire) ...[
                const SizedBox(height: 20),
                _RecentList(
                  title: 'Derniers clients',
                  actionLabel: 'Voir tout',
                  onTap: () => onOpenSection('clients'),
                  load: () => api.list('/api/clients'),
                  rowBuilder: (item) => _ClientPreviewRow(client: item),
                ),
                const SizedBox(height: 14),
                _RecentList(
                  title: 'Derniers contrats',
                  actionLabel: 'Voir tout',
                  onTap: () => onOpenSection('contracts'),
                  load: () => api.list('/api/contrats'),
                  rowBuilder: (item) => _ContractPreviewRow(contract: item),
                ),
              ],
              if (user.isClient && stats['timeline'] is List) ...[
                const SizedBox(height: 20),
                _PanelTitle(title: 'Activité récente', onTap: refresh, actionLabel: 'Actualiser'),
                const SizedBox(height: 8),
                ...List<dynamic>.from(stats['timeline'] as List).map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: CompactTile(
                      icon: Icons.history,
                      title: '${item['title'] ?? 'Activité'}',
                      subtitle: '${item['description'] ?? ''}',
                      trailing: null,
                      showChevron: false,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 40),
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

class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader({required this.user, required this.unreadNotifications, required this.onBellTap});

  final AppUser user;
  final int unreadNotifications;
  final VoidCallback onBellTap;

  @override
  Widget build(BuildContext context) {
    final firstName = user.name.trim().isEmpty ? '' : user.name.trim().split(' ').first;
    return Row(
      children: [
        Expanded(
          child: Text.rich(
            TextSpan(
              style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 26),
              children: [
                const TextSpan(text: 'Bonjour, '),
                TextSpan(text: firstName),
                const TextSpan(text: '  👋'),
              ],
            ),
          ),
        ),
        Stack(
          clipBehavior: Clip.none,
          children: [
            IconButton(
              onPressed: onBellTap,
              icon: const Icon(Icons.notifications_outlined, color: AppColors.text),
            ),
            if (unreadNotifications > 0)
              Positioned(
                right: 6,
                top: 6,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                  decoration: BoxDecoration(
                    color: AppColors.primaryGold,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                  child: Text(
                    '$unreadNotifications',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.background, fontSize: 10, fontWeight: FontWeight.w900),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _RecentList extends StatelessWidget {
  const _RecentList({
    required this.title,
    required this.actionLabel,
    required this.onTap,
    required this.load,
    required this.rowBuilder,
  });

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
          const SizedBox(height: 10),
          FutureBuilder<List<dynamic>>(
            future: load(),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Padding(
                  padding: EdgeInsets.all(18),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              final rows = (snapshot.data ?? const []).take(4).toList();
              if (rows.isEmpty) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Text('Aucune donnée pour le moment.', style: TextStyle(color: AppColors.muted)),
                );
              }
              return Column(
                children: [for (final row in rows) rowBuilder(row)],
              );
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
        Expanded(
          child: Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: AppColors.text)),
        ),
        if (actionLabel != null)
          TextButton(
            onPressed: onTap,
            child: Text(actionLabel!, style: const TextStyle(fontWeight: FontWeight.w800)),
          ),
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
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: ListTile(
        contentPadding: EdgeInsets.zero,
        leading: GoldAvatar(label: name, radius: 20),
        title: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.text)),
        subtitle: Text(
          '${client['ville'] ?? client['city'] ?? client['adresse'] ?? ''}',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: AppColors.muted),
        ),
        trailing: StatusPill(status: client['statut'] ?? client['status'] ?? 'actif', compact: true),
      ),
    );
  }
}

class _ContractPreviewRow extends StatelessWidget {
  const _ContractPreviewRow({required this.contract});

  final dynamic contract;

  @override
  Widget build(BuildContext context) {
    // titre_contrat is the dynamic, freely-typed contract name from the
    // creation wizard — shown here in preference to any generated reference.
    final title = '${contract['titre_contrat'] ?? contract['reference'] ?? contract['numero'] ?? 'Contrat'}';
    final client = contract['entreprise'] is Map ? contract['entreprise']['raison_sociale'] : contract['client'];
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900),
        ),
        subtitle: Text('${client ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted)),
        trailing: StatusPill(status: contract['statut'] ?? contract['status'] ?? 'brouillon', compact: true),
      ),
    );
  }
}