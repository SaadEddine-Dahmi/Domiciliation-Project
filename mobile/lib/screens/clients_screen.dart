// lib/screens/clients_screen.dart
//
// Clients list + client detail, redesigned to match the mockup:
//   - List: search bar, live count ("32 clients"), rows with initials
//     avatar, name, city, status pill. Gold "+" FAB to create a client.
//   - Detail: large avatar header with status + "client since" date,
//     email/phone quick-contact row, an "Informations clés" 2x2 grid
//     (adresse, forme juridique, capital social, RC), then a documents
//     list with eye (preview) / download / delete actions per row.
//
// Data shape expected (ClientController::index / show):
//   { id, raison_sociale, forme_juridique, adresse, ville, pays, capital,
//     statut, created_at, representant: {...}, clientUser: {email, telephone},
//     documents: [{ id, name, extension, created_at, download_url, preview_url }] }
//
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/compact_tile.dart';
import '../widgets/error_banner.dart';
import '../widgets/gold_avatar.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';
import 'client_form_screen.dart';

class ClientsScreen extends StatefulWidget {
  const ClientsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ClientsScreen> createState() => _ClientsScreenState();
}

class _ClientsScreenState extends State<ClientsScreen> {
  List<dynamic> clients = [];
  bool loading = true;
  String? error;
  final search = TextEditingController();

  @override
  void initState() {
    super.initState();
    load();
    search.addListener(() => setState(() {}));
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
      final rows = await widget.api.list('/api/clients');
      if (!mounted) return;
      setState(() {
        clients = rows;
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

  List<dynamic> get filtered {
    final query = search.text.trim().toLowerCase();
    if (query.isEmpty) return clients;
    return clients.where((client) {
      final name = '${client['raison_sociale'] ?? ''}'.toLowerCase();
      final city = '${client['ville'] ?? ''}'.toLowerCase();
      final email =
          '${client['clientUser']?['email'] ?? client['client_user']?['email'] ?? ''}'
              .toLowerCase();
      return name.contains(query) ||
          city.contains(query) ||
          email.contains(query);
    }).toList();
  }

  Future<void> openCreate() async {
    final created = await Navigator.push<dynamic>(
      context,
      MaterialPageRoute(builder: (_) => ClientFormScreen(api: widget.api)),
    );
    if (created != null) await load();
  }

  Future<void> openDetail(dynamic client) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
          builder: (_) =>
              ClientDetailScreen(api: widget.api, clientId: client['id'])),
    );
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final rows = filtered;
    return Scaffold(
      appBar: AppBar(title: const Text('Clients')),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: [
            TextField(
              controller: search,
              decoration: const InputDecoration(
                hintText: 'Rechercher un client...',
                prefixIcon:
                    Icon(Icons.search, color: AppColors.muted, size: 20),
              ),
            ),
            const SizedBox(height: 14),
            Text('${rows.length} client${rows.length > 1 ? 's' : ''}',
                style: const TextStyle(
                    color: AppColors.primaryGold, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            if (error != null) ...[
              ErrorBanner(message: error!),
              const SizedBox(height: 12),
            ],
            if (loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 60),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (rows.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 60),
                child: Column(
                  children: [
                    const Icon(Icons.business_outlined,
                        size: 44, color: AppColors.faint),
                    const SizedBox(height: 10),
                    Text(
                      search.text.trim().isEmpty
                          ? 'Aucun client'
                          : 'Aucun résultat pour "${search.text.trim()}"',
                      style: const TextStyle(color: AppColors.muted),
                    ),
                  ],
                ),
              )
            else
              for (final client in rows)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: CompactTile(
                    avatarLabel: '${client['raison_sociale'] ?? '?'}',
                    title: '${client['raison_sociale'] ?? 'Client'}',
                    subtitle: '${client['ville'] ?? ''}',
                    trailing: '${client['statut'] ?? 'actif'}',
                    onTap: () => openDetail(client),
                  ),
                ),
            const SizedBox(height: 80),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: openCreate,
        backgroundColor: AppColors.primaryGold,
        foregroundColor: AppColors.background,
        child: const Icon(Icons.add),
      ),
    );
  }
}

class ClientDetailScreen extends StatefulWidget {
  const ClientDetailScreen(
      {super.key, required this.api, required this.clientId});

  final ApiClient api;
  final Object clientId;

  @override
  State<ClientDetailScreen> createState() => _ClientDetailScreenState();
}

class _ClientDetailScreenState extends State<ClientDetailScreen> {
  Map<String, dynamic>? client;
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
      final data = await widget.api.getClient(widget.clientId);
      if (!mounted) return;
      setState(() {
        client = Map<String, dynamic>.from(data as Map);
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

  Future<void> openEdit() async {
    final updated = await Navigator.push<dynamic>(
      context,
      MaterialPageRoute(
          builder: (_) => ClientFormScreen(api: widget.api, client: client)),
    );
    if (updated != null) await load();
  }

  Future<void> deleteDocument(dynamic doc) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Supprimer ce document ?'),
        content: Text(
            '${doc['name'] ?? 'Ce document'} sera définitivement supprimé.'),
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
    if (confirmed != true) return;

    try {
      await widget.api.deleteDocument(doc['id']);
      await load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e is ApiException ? e.message : e.toString())),
      );
    }
  }

  String _fmtDate(dynamic raw) {
    if (raw == null) return '-';
    final parsed = DateTime.tryParse('$raw');
    if (parsed == null) return '$raw';
    return '${parsed.day.toString().padLeft(2, '0')}/${parsed.month.toString().padLeft(2, '0')}/${parsed.year}';
  }

  IconData _iconForExtension(String ext) {
    switch (ext.toLowerCase()) {
      case 'pdf':
        return Icons.picture_as_pdf_outlined;
      case 'doc':
      case 'docx':
        return Icons.description_outlined;
      case 'jpg':
      case 'jpeg':
      case 'png':
        return Icons.image_outlined;
      case 'xls':
      case 'xlsx':
        return Icons.table_chart_outlined;
      default:
        return Icons.insert_drive_file_outlined;
    }
  }

  Color _colorForExtension(String ext) {
    switch (ext.toLowerCase()) {
      case 'pdf':
        return AppColors.red;
      case 'doc':
      case 'docx':
        return AppColors.blue;
      case 'xls':
      case 'xlsx':
        return AppColors.green;
      default:
        return AppColors.primaryGold;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Détail client'),
        actions: [
          IconButton(
              onPressed: loading ? null : openEdit,
              icon: const Icon(Icons.edit_outlined)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Padding(
                  padding: const EdgeInsets.all(AppSpacing.page),
                  child: ErrorBanner(message: error!))
              : RefreshIndicator(
                  onRefresh: load,
                  child: _buildBody(context, client!),
                ),
    );
  }

  Widget _buildBody(BuildContext context, Map<String, dynamic> client) {
    final name = '${client['raison_sociale'] ?? 'Client'}';
    final contact = client['clientUser'] ?? client['client_user'];
    final email = contact is Map ? '${contact['email'] ?? '-'}' : '-';
    final phone = contact is Map ? '${contact['telephone'] ?? '-'}' : '-';
    final documents =
        List<dynamic>.from(client['documents'] as List? ?? const []);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.page),
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  GoldAvatar(label: name, radius: 34),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(name,
                            style: Theme.of(context)
                                .textTheme
                                .titleLarge
                                ?.copyWith(fontSize: 20)),
                        const SizedBox(height: 6),
                        StatusPill(status: client['statut'] ?? 'actif'),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                'Client depuis le ${_fmtDate(client['created_at'] ?? client['date_creation'])}',
                style: const TextStyle(color: AppColors.muted, fontSize: 12.5),
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.surfaceRaised,
                  borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                ),
                child: Row(
                  children: [
                    Expanded(
                        child: _ContactItem(
                            icon: Icons.mail_outline,
                            label: 'Email',
                            value: email)),
                    Container(width: 1, height: 34, color: AppColors.border),
                    const SizedBox(width: 12),
                    Expanded(
                        child: _ContactItem(
                            icon: Icons.call_outlined,
                            label: 'Téléphone',
                            value: phone)),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const Text('INFORMATIONS CLÉS',
            style: TextStyle(
                color: AppColors.primaryGold,
                fontWeight: FontWeight.w900,
                fontSize: 12,
                letterSpacing: 1)),
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: _InfoTile(
                icon: Icons.location_on_outlined,
                label: 'Adresse',
                value:
                    '${client['adresse'] ?? '-'}${client['ville'] != null ? '\n${client['ville']}' : ''}',
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _InfoTile(
                icon: Icons.balance_outlined,
                label: 'Forme juridique',
                value: '${client['forme_juridique'] ?? '-'}',
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: _InfoTile(
                icon: Icons.payments_outlined,
                label: 'Capital social',
                value:
                    client['capital'] != null ? '${client['capital']} DH' : '-',
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _InfoTile(
                icon: Icons.badge_outlined,
                label: 'N° RC',
                value:
                    '${client['rc'] ?? client['representant']?['cin'] ?? '-'}',
              ),
            ),
          ],
        ),
        const SizedBox(height: 22),
        Row(
          children: [
            const Expanded(
              child: Text('DOCUMENTS',
                  style: TextStyle(
                      color: AppColors.primaryGold,
                      fontWeight: FontWeight.w900,
                      fontSize: 12,
                      letterSpacing: 1)),
            ),
            TextButton(onPressed: () {}, child: const Text('Voir tout')),
          ],
        ),
        const SizedBox(height: 8),
        if (documents.isEmpty)
          const PremiumCard(
              child: Text('Aucun document',
                  style: TextStyle(color: AppColors.muted)))
        else
          for (final doc in documents)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _DocumentRow(
                doc: doc,
                icon: _iconForExtension('${doc['extension'] ?? ''}'),
                color: _colorForExtension('${doc['extension'] ?? ''}'),
                dateLabel: _fmtDate(doc['created_at']),
                onPreview: () =>
                    openExternal(widget.api.documentPreviewUrl(doc['id'])),
                onDownload: () =>
                    openExternal(widget.api.documentDownloadUrl(doc['id'])),
                onDelete: () => deleteDocument(doc),
              ),
            ),
        const SizedBox(height: 40),
      ],
    );
  }
}

class _ContactItem extends StatelessWidget {
  const _ContactItem(
      {required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: AppColors.primaryGold),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label,
                  style:
                      const TextStyle(color: AppColors.muted, fontSize: 10.5)),
              Text(value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      color: AppColors.text,
                      fontWeight: FontWeight.w700,
                      fontSize: 12.5)),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile(
      {required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 17, color: AppColors.primaryGold),
          const SizedBox(height: 8),
          Text(label,
              style: const TextStyle(color: AppColors.muted, fontSize: 11)),
          const SizedBox(height: 3),
          Text(value,
              style: const TextStyle(
                  color: AppColors.text,
                  fontWeight: FontWeight.w800,
                  fontSize: 13.5)),
        ],
      ),
    );
  }
}

class _DocumentRow extends StatelessWidget {
  const _DocumentRow({
    required this.doc,
    required this.icon,
    required this.color,
    required this.dateLabel,
    required this.onPreview,
    required this.onDownload,
    required this.onDelete,
  });

  final dynamic doc;
  final IconData icon;
  final Color color;
  final String dateLabel;
  final VoidCallback onPreview;
  final VoidCallback onDownload;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
                color: color.withOpacity(0.14),
                borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, size: 18, color: color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${doc['name'] ?? 'Document'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 13.5)),
                const SizedBox(height: 2),
                Text(dateLabel,
                    style: const TextStyle(
                        color: AppColors.muted, fontSize: 11.5)),
              ],
            ),
          ),
          IconButton(
              onPressed: onPreview,
              icon: const Icon(Icons.visibility_outlined,
                  size: 19, color: AppColors.muted)),
          IconButton(
              onPressed: onDownload,
              icon: const Icon(Icons.download_outlined,
                  size: 19, color: AppColors.primaryGold)),
          IconButton(
              onPressed: onDelete,
              icon: const Icon(Icons.delete_outline,
                  size: 19, color: AppColors.red)),
        ],
      ),
    );
  }
}
