import 'dart:ui';

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/theme_controller.dart';
import '../models/app_user.dart';
import '../theme/app_design.dart';
import 'admin_approvals_screen.dart';
import 'admin_domiciliataires_screen.dart';
import 'articles_screen.dart';
import 'client_form_screen.dart';
import 'clients_screen.dart';
import 'contract_form_screen.dart';
import 'contracts_screen.dart';
import 'dashboard_screen.dart';
import 'documents_screen.dart';
import 'factures_screen.dart';
import 'messages_screen.dart';
import 'notifications_screen.dart';
import 'payments_screen.dart';
import 'profile_screen.dart';
import 'settings_screen.dart';
import 'templates_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({
    super.key,
    required this.api,
    required this.user,
    required this.themeController,
    required this.onLogout,
  });

  final ApiClient api;
  final AppUser user;
  final ThemeController themeController;
  final Future<void> Function() onLogout;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int index = 0;
  int unreadNotifications = 0;

  @override
  void initState() {
    super.initState();
    refreshBadges();
  }

  Future<void> refreshBadges() async {
    try {
      final count = await widget.api.unreadNotificationsCount();
      if (mounted) setState(() => unreadNotifications = count);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      DashboardScreen(api: widget.api, user: widget.user, onOpenSection: openSection),
      if (widget.user.isAdmin) AdminDomiciliatairesScreen(api: widget.api),
      if (widget.user.isDomiciliataire) ClientsScreen(api: widget.api),
      if (!widget.user.isAdmin) ContractsScreen(api: widget.api),
      if (widget.user.isDomiciliataire) FacturesScreen(api: widget.api),
      ProfileScreen(api: widget.api, user: widget.user),
    ];

    if (index >= pages.length) index = 0;

    final desktop = isDesktop(context);
    if (desktop) {
      return Scaffold(
        body: Row(
          children: [
            PremiumSidebar(
              api: widget.api,
              user: widget.user,
              unreadNotifications: unreadNotifications,
              themeController: widget.themeController,
              selectedIndex: index,
              onPrimarySelected: (value) => setState(() => index = value),
              onOpenSection: openSection,
              onLogout: widget.onLogout,
              permanent: true,
            ),
            Expanded(child: pages[index]),
          ],
        ),
      );
    }

    return Scaffold(
      drawerScrimColor: Colors.black.withOpacity(0.46),
      drawer: PremiumSidebar(
        api: widget.api,
        user: widget.user,
        unreadNotifications: unreadNotifications,
        themeController: widget.themeController,
        selectedIndex: index,
        onPrimarySelected: (value) => setState(() => index = value),
        onOpenSection: openSection,
        onLogout: widget.onLogout,
        permanent: false,
      ),
      appBar: AppBar(
        leading: Builder(
          builder: (context) => IconButton(
            tooltip: 'Menu',
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
        title: Center(child: Text(pageTitle(index))),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationsScreen(api: widget.api))),
            icon: Badge(
              isLabelVisible: unreadNotifications > 0,
              label: Text(unreadNotifications > 99 ? '99+' : '$unreadNotifications'),
              child: const Icon(Icons.notifications_none_rounded),
            ),
          ),
        ],
      ),
      body: pages[index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        destinations: [
          const NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Accueil'),
          if (widget.user.isAdmin)
            const NavigationDestination(icon: Icon(Icons.admin_panel_settings_outlined), selectedIcon: Icon(Icons.admin_panel_settings), label: 'Admins'),
          if (widget.user.isDomiciliataire)
            const NavigationDestination(icon: Icon(Icons.business_outlined), selectedIcon: Icon(Icons.business), label: 'Clients'),
          if (!widget.user.isAdmin)
            const NavigationDestination(icon: Icon(Icons.description_outlined), selectedIcon: Icon(Icons.description), label: 'Contrats'),
          if (widget.user.isDomiciliataire)
            const NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long), label: 'Factures'),
          const NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profil'),
        ],
        onDestinationSelected: (value) => setState(() => index = value),
      ),
    );
  }

  String pageTitle(int value) {
    final labels = [
      'Tableau de bord',
      if (widget.user.isAdmin) 'Domiciliataires',
      if (widget.user.isDomiciliataire) 'Clients',
      if (!widget.user.isAdmin) 'Contrats',
      if (widget.user.isDomiciliataire) 'Factures',
      'Profil',
    ];
    return labels[value];
  }

  void openSection(String section) {
    if (section == 'new_client') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => ClientFormScreen(api: widget.api)));
      return;
    }
    if (section == 'new_contract') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => ContractFormScreen(api: widget.api)));
      return;
    }

    final sections = [
      'dashboard',
      if (widget.user.isAdmin) 'domiciliataires',
      if (widget.user.isDomiciliataire) 'clients',
      if (!widget.user.isAdmin) 'contracts',
      if (widget.user.isDomiciliataire) 'factures',
      'profile',
    ];
    final next = sections.indexOf(section);
    if (next >= 0) setState(() => index = next);
    if (section == 'approvals') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => AdminApprovalsScreen(api: widget.api)));
    }
    if (section == 'messages') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => MessagesScreen(api: widget.api)));
    }
  }
}

class PremiumSidebar extends StatelessWidget {
  const PremiumSidebar({
    super.key,
    required this.api,
    required this.user,
    required this.unreadNotifications,
    required this.themeController,
    required this.selectedIndex,
    required this.onPrimarySelected,
    required this.onOpenSection,
    required this.onLogout,
    required this.permanent,
  });

  final ApiClient api;
  final AppUser user;
  final int unreadNotifications;
  final ThemeController themeController;
  final int selectedIndex;
  final void Function(int index) onPrimarySelected;
  final void Function(String section) onOpenSection;
  final Future<void> Function() onLogout;
  final bool permanent;

  @override
  Widget build(BuildContext context) {
    final role = user.role == 'domiciliataire' ? 'Domiciliataire' : user.role == 'admin' ? 'Super Admin' : 'Client';
    final content = SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.primaryGold),
                  ),
                  child: const Icon(Icons.account_balance_outlined, color: AppColors.primaryGold),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Text(
                    'Domiciliation App',
                    style: TextStyle(fontFamily: 'Fraunces', fontSize: 19, fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.surfaceRaised,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  _SidebarAvatar(api: api, user: user),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900)),
                        Text(role, style: const TextStyle(color: AppColors.primaryGold, fontSize: 12, fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Expanded(
              child: ListView(
                children: [
                  SidebarSection(title: 'PRINCIPAL'),
                  SidebarItem(icon: Icons.dashboard_outlined, label: 'Tableau de bord', active: selectedIndex == 0, onTap: () => selectPrimary(context, 0)),
                  SidebarItem(icon: Icons.person_outline, label: 'Mon Profil', active: selectedIndex == profileIndex, onTap: () => selectPrimary(context, profileIndex)),
                  if (user.isAdmin)
                    SidebarItem(icon: Icons.admin_panel_settings_outlined, label: 'Domiciliataires', active: selectedIndex == 1, onTap: () => selectPrimary(context, 1)),
                  if (user.isAdmin)
                    SidebarItem(icon: Icons.check_circle_outline, label: 'Approbations', onTap: () => push(context, AdminApprovalsScreen(api: api), closeDrawer: !permanent)),
                  if (user.isDomiciliataire)
                    SidebarItem(icon: Icons.business_outlined, label: 'Mes Clients', active: selectedIndex == 1, onTap: () => selectPrimary(context, 1)),
                  SidebarItem(icon: Icons.folder_outlined, label: 'Documents', onTap: () => push(context, DocumentsScreen(api: api))),
                  if (user.isDomiciliataire)
                    SidebarItem(icon: Icons.payments_outlined, label: 'Factures & Paiements', onTap: () => push(context, PaymentsScreen(api: api), closeDrawer: !permanent)),
                  if (user.isDomiciliataire)
                    SidebarItem(icon: Icons.receipt_long_outlined, label: 'Toutes les Factures', active: selectedIndex == facturesIndex, onTap: () => selectPrimary(context, facturesIndex)),
                  SidebarSection(title: 'OUTILS'),
                  if (user.isDomiciliataire)
                    SidebarItem(icon: Icons.note_add_outlined, label: 'Nouveau Contrat', onTap: () => onOpenSection('new_contract')),
                  if (!user.isAdmin)
                    SidebarItem(icon: Icons.description_outlined, label: 'Mes Contrats', active: selectedIndex == contractsIndex, onTap: () => selectPrimary(context, contractsIndex)),
                  SidebarItem(icon: Icons.scanner, label: 'Scanner / Importer', onTap: () => push(context, DocumentsScreen(api: api))),
                  if (user.isDomiciliataire)
                    SidebarItem(icon: Icons.article_outlined, label: 'Articles', onTap: () => push(context, ArticlesScreen(api: api), closeDrawer: !permanent)),
                  if (user.isDomiciliataire)
                    SidebarItem(icon: Icons.dashboard, label: 'Templates', onTap: () => push(context, TemplatesScreen(api: api), closeDrawer: !permanent)),
                  SidebarSection(title: 'COMMUNICATION'),
                  SidebarItem(icon: Icons.mail_outline, label: 'Messages clients', onTap: () => push(context, MessagesScreen(api: api))),
                  SidebarItem(
                    icon: Icons.notifications_outlined,
                    label: 'Notifications',
                    badge: unreadNotifications,
                    onTap: () => push(context, NotificationsScreen(api: api)),
                  ),
                  SidebarItem(
                    icon: Icons.settings_outlined,
                    label: 'Parametres',
                    onTap: () => push(
                      context,
                      SettingsScreen(api: api, user: user, themeController: themeController),
                      closeDrawer: !permanent,
                    ),
                  ),
                  SidebarItem(
                    icon: themeController.isDark ? Icons.light_mode_outlined : Icons.dark_mode_outlined,
                    label: themeController.isDark ? 'Mode clair' : 'Mode sombre',
                    onTap: themeController.toggleDarkLight,
                  ),
                ],
              ),
            ),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () async {
                  if (!permanent) Navigator.pop(context);
                  await onLogout();
                },
                icon: const Icon(Icons.logout),
                label: const Text('Deconnexion'),
              ),
            ),
          ],
        ),
      ),
    );

    final panel = Container(width: 316, color: AppColors.surface, child: content);
    if (permanent) return panel;

    return BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
      child: Drawer(
        width: 316,
        backgroundColor: AppColors.surface,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.horizontal(right: Radius.circular(26)),
        ),
        child: content,
      ),
    );
  }

  int get contractsIndex => user.isDomiciliataire ? 2 : 1;
  int get facturesIndex => user.isDomiciliataire ? 3 : 0;
  int get profileIndex => user.isDomiciliataire ? 4 : user.isAdmin ? 2 : 2;

  void selectPrimary(BuildContext context, int value) {
    if (!permanent) Navigator.pop(context);
    onPrimarySelected(value);
  }

  static String initials(String name) {
    final words = name.trim().split(RegExp(r'\s+')).where((item) => item.isNotEmpty).toList();
    if (words.isEmpty) return 'U';
    return words.take(2).map((item) => item[0]).join().toUpperCase();
  }

  static void push(BuildContext context, Widget screen, {bool closeDrawer = true}) {
    if (closeDrawer && Navigator.canPop(context)) Navigator.pop(context);
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }

  static void showSoon(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Module disponible dans la prochaine phase mobile.')),
    );
  }
}

class _SidebarAvatar extends StatefulWidget {
  const _SidebarAvatar({required this.api, required this.user});

  final ApiClient api;
  final AppUser user;

  @override
  State<_SidebarAvatar> createState() => _SidebarAvatarState();
}

class _SidebarAvatarState extends State<_SidebarAvatar> {
  String? photoUrl;

  @override
  void initState() {
    super.initState();
    loadPhoto();
  }

  @override
  void didUpdateWidget(covariant _SidebarAvatar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.id != widget.user.id || oldWidget.api.baseUrl != widget.api.baseUrl) {
      loadPhoto();
    }
  }

  Future<void> loadPhoto() async {
    try {
      final profile = await widget.api.profile().timeout(const Duration(seconds: 8));
      final raw = '${profile['photo_url'] ?? ''}'.trim();
      if (!mounted) return;
      setState(() => photoUrl = raw.isEmpty ? null : resolvePhotoUrl(raw));
    } catch (_) {
      if (mounted) setState(() => photoUrl = null);
    }
  }

  String resolvePhotoUrl(String raw) {
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    final base = widget.api.baseUrl.replaceFirst(RegExp(r'/$'), '');
    final path = raw.startsWith('/') ? raw : '/$raw';
    return '$base$path';
  }

  @override
  Widget build(BuildContext context) {
    return CircleAvatar(
      radius: 24,
      backgroundColor: AppColors.primaryGold,
      backgroundImage: photoUrl == null ? null : NetworkImage(photoUrl!),
      child: photoUrl == null
          ? Text(
              PremiumSidebar.initials(widget.user.name),
              style: const TextStyle(color: AppColors.background, fontWeight: FontWeight.w900),
            )
          : null,
    );
  }
}

class SidebarSection extends StatelessWidget {
  const SidebarSection({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 18, 10, 8),
      child: Text(
        title,
        style: const TextStyle(color: AppColors.soft, fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 0),
      ),
    );
  }
}

class SidebarItem extends StatelessWidget {
  const SidebarItem({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.badge = 0,
    this.active = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final int badge;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        onTap: onTap,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        tileColor: active ? AppColors.primaryGold.withOpacity(0.12) : Colors.transparent,
        leading: Icon(icon, color: AppColors.primaryGold),
        title: Text(label, style: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w800)),
        trailing: badge > 0
            ? Badge(label: Text(badge > 99 ? '99+' : '$badge'))
            : const Icon(Icons.chevron_right, color: AppColors.soft),
      ),
    );
  }
}
