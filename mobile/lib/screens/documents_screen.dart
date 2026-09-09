// lib/screens/documents_screen.dart
//
// Documents — redesigned to match the mockup: client filter dropdown,
// search bar, and a list where each row shows the file icon, name,
// category subtitle, and two right-aligned date columns (Date d'import /
// Date d'expiration) color-coded by urgency. A trailing "..." menu offers
// preview / download / delete.
//
// Upload flow is preserved from the previous version but moved into a
// bottom sheet triggered by the FAB, since the mockup's main list view has
// no inline upload card — matches the "Scanner / Importer" pattern used
// elsewhere in the app (scan.vue on web).

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_button.dart';
import '../widgets/premium_card.dart';

class DocumentsScreen extends StatefulWidget {
  const DocumentsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<DocumentsScreen> createState() => _DocumentsScreenState();
}

class _DocumentsScreenState extends State<DocumentsScreen> {
  List<dynamic> documents = [];
  List<dynamic> clients = [];
  List<dynamic> documentTypes = [];
  Object? filterClientId;
  final search = TextEditingController();
  bool loading = true;
  String? error;

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
      final loadedDocuments = await widget.api.list('/api/documents');
      final loadedClients = await widget.api.list('/api/clients');
      final loadedTypes = await widget.api.documentTypes();
      if (!mounted) return;
      setState(() {
        documents = loadedDocuments;
        clients = loadedClients;
        documentTypes = loadedTypes;
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
    var rows = documents;
    if (filterClientId != null) {
      rows = rows
          .where((d) =>
              d['entreprise_id'] == filterClientId ||
              d['entreprise']?['id'] == filterClientId)
          .toList();
    }
    final query = search.text.trim().toLowerCase();
    if (query.isNotEmpty) {
      rows = rows.where((d) {
        final name = '${d['name'] ?? ''}'.toLowerCase();
        final type = '${d['document_type']?['name'] ?? ''}'.toLowerCase();
        return name.contains(query) || type.contains(query);
      }).toList();
    }
    return rows;
  }

  IconData _iconFor(String ext) {
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

  Color _colorFor(String ext) {
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

  String _fmt(dynamic raw) {
    if (raw == null) return '-';
    final parsed = DateTime.tryParse('$raw');
    if (parsed == null) return '$raw';
    return '${parsed.day.toString().padLeft(2, '0')}/${parsed.month.toString().padLeft(2, '0')}/${parsed.year}';
  }

  /// Expiration date color: red if already past, amber if within 30 days,
  /// green otherwise. Mirrors the color logic used in the web app.
  Color _expiryColor(dynamic raw) {
    if (raw == null) return AppColors.muted;
    final parsed = DateTime.tryParse('$raw');
    if (parsed == null) return AppColors.muted;
    final days = parsed.difference(DateTime.now()).inDays;
    if (days < 0) return AppColors.red;
    if (days < 30) return AppColors.amber;
    return AppColors.green;
  }

  Future<void> openUploadSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _UploadSheet(
        api: widget.api,
        clients: clients,
        documentTypes: documentTypes,
        onUploaded: load,
        onCreateType: (created) =>
            setState(() => documentTypes = [...documentTypes, created]),
      ),
    );
  }

  void showRowMenu(dynamic doc) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius:
            BorderRadius.vertical(top: Radius.circular(AppSpacing.radiusLg)),
      ),
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading:
                  const Icon(Icons.visibility_outlined, color: AppColors.text),
              title: const Text('Aperçu'),
              onTap: () {
                Navigator.pop(context);
                openExternal(widget.api.documentPreviewUrl(doc['id']));
              },
            ),
            ListTile(
              leading: const Icon(Icons.download_outlined,
                  color: AppColors.primaryGold),
              title: const Text('Télécharger'),
              onTap: () {
                Navigator.pop(context);
                openExternal(widget.api.documentDownloadUrl(doc['id']));
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.red),
              title: const Text('Supprimer',
                  style: TextStyle(color: AppColors.red)),
              onTap: () async {
                Navigator.pop(context);
                final confirmed = await showDialog<bool>(
                  context: context,
                  builder: (_) => AlertDialog(
                    title: const Text('Supprimer ce document ?'),
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
                if (confirmed == true) {
                  // TODO(api_client): confirm deleteDocument(id) signature.
                  await widget.api.deleteDocument(doc['id']);
                  await load();
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Documents')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.page),
                children: [
                  if (error != null) ...[
                    ErrorBanner(message: error!),
                    const SizedBox(height: 14),
                  ],
                  DropdownButtonFormField<Object?>(
                    value: filterClientId,
                    decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.business_outlined, size: 18)),
                    items: [
                      const DropdownMenuItem<Object?>(
                          value: null, child: Text('Tous les clients')),
                      for (final client in clients)
                        DropdownMenuItem<Object?>(
                          value: client['id'],
                          child: Text('${client['raison_sociale'] ?? 'Client'}',
                              overflow: TextOverflow.ellipsis),
                        ),
                    ],
                    onChanged: (value) =>
                        setState(() => filterClientId = value),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: search,
                    decoration: const InputDecoration(
                      hintText: 'Rechercher un document...',
                      prefixIcon:
                          Icon(Icons.search, color: AppColors.muted, size: 20),
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (filtered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 50),
                      child: Center(
                          child: Text('Aucun document',
                              style: TextStyle(color: AppColors.muted))),
                    )
                  else
                    for (final doc in filtered)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _DocumentRow(
                          doc: doc,
                          icon: _iconFor('${doc['extension'] ?? ''}'),
                          color: _colorFor('${doc['extension'] ?? ''}'),
                          importLabel: _fmt(doc['created_at']),
                          expirationLabel: doc['date_expiration'] == null
                              ? '—'
                              : _fmt(doc['date_expiration']),
                          expirationColor: _expiryColor(doc['date_expiration']),
                          onTap: () => showRowMenu(doc),
                        ),
                      ),
                  const SizedBox(height: 80),
                ],
              ),
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: openUploadSheet,
        backgroundColor: AppColors.primaryGold,
        foregroundColor: AppColors.background,
        child: const Icon(Icons.upload_file_outlined),
      ),
    );
  }
}

class _DocumentRow extends StatelessWidget {
  const _DocumentRow({
    required this.doc,
    required this.icon,
    required this.color,
    required this.importLabel,
    required this.expirationLabel,
    required this.expirationColor,
    required this.onTap,
  });

  final dynamic doc;
  final IconData icon;
  final Color color;
  final String importLabel;
  final String expirationLabel;
  final Color expirationColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
                color: color.withOpacity(0.14),
                borderRadius: BorderRadius.circular(11)),
            child: Icon(icon, size: 19, color: color),
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
                Text('${doc['document_type']?['name'] ?? ''}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        color: AppColors.muted, fontSize: 11.5)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(importLabel,
                  style: const TextStyle(color: AppColors.muted, fontSize: 11)),
              const SizedBox(height: 3),
              Text(expirationLabel,
                  style: TextStyle(
                      color: expirationColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w800)),
            ],
          ),
          IconButton(
              onPressed: onTap,
              icon: const Icon(Icons.more_vert,
                  color: AppColors.faint, size: 20)),
        ],
      ),
    );
  }
}

/// Upload bottom sheet — same fields as the previous inline card
/// (client, document type or create-new-type inline, expiration date,
/// file picker) but presented modally so the main list matches the mock.
class _UploadSheet extends StatefulWidget {
  const _UploadSheet({
    required this.api,
    required this.clients,
    required this.documentTypes,
    required this.onUploaded,
    required this.onCreateType,
  });

  final ApiClient api;
  final List<dynamic> clients;
  final List<dynamic> documentTypes;
  final Future<void> Function() onUploaded;
  final ValueChanged<dynamic> onCreateType;

  @override
  State<_UploadSheet> createState() => _UploadSheetState();
}

class _UploadSheetState extends State<_UploadSheet> {
  PlatformFile? file;
  Object? clientId;
  Object? typeId;
  final expiration = TextEditingController();
  final newTypeName = TextEditingController();
  bool newTypeExpires = false;
  bool showNewType = false;
  bool uploading = false;
  bool creatingType = false;
  String? error;

  @override
  void dispose() {
    expiration.dispose();
    newTypeName.dispose();
    super.dispose();
  }

  Future<void> pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    final picked = result?.files.single;
    if (picked != null) setState(() => file = picked);
  }

  Future<void> createType() async {
    if (newTypeName.text.trim().isEmpty) return;
    setState(() => creatingType = true);
    try {
      final created = await widget.api.createDocumentType({
        'name': newTypeName.text.trim(),
        'has_expiration': newTypeExpires,
        'is_required': false,
      });
      widget.onCreateType(created);
      setState(() {
        typeId = created['id'];
        showNewType = false;
        newTypeName.clear();
      });
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => creatingType = false);
    }
  }

  Future<void> upload() async {
    if (file == null || clientId == null || typeId == null) {
      setState(() => error = 'Sélectionnez un client, un type et un fichier.');
      return;
    }
    setState(() {
      uploading = true;
      error = null;
    });
    try {
      await widget.api.uploadDocument(
        entrepriseId: clientId!,
        documentTypeId: typeId!,
        file: file!,
        dateExpiration: expiration.text,
      );
      if (!mounted) return;
      Navigator.pop(context);
      await widget.onUploaded();
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius:
              BorderRadius.vertical(top: Radius.circular(AppSpacing.radiusLg)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                    width: 36,
                    height: 4,
                    decoration: BoxDecoration(
                        color: AppColors.border,
                        borderRadius: BorderRadius.circular(2))),
              ),
              const SizedBox(height: 16),
              const Text('Importer un document',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
              const SizedBox(height: 16),
              if (error != null) ...[
                ErrorBanner(message: error!),
                const SizedBox(height: 12)
              ],
              InkWell(
                onTap: pickFile,
                borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceRaised,
                    borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                    border: Border.all(
                        color: file == null
                            ? AppColors.border
                            : AppColors.green.withOpacity(0.5)),
                  ),
                  child: Column(
                    children: [
                      Icon(
                          file == null
                              ? Icons.upload_file_outlined
                              : Icons.task_outlined,
                          color: file == null
                              ? AppColors.primaryGold
                              : AppColors.green,
                          size: 30),
                      const SizedBox(height: 6),
                      Text(
                          file == null ? 'Sélectionner un fichier' : file!.name,
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontWeight: FontWeight.w800)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<Object>(
                value: clientId,
                decoration: const InputDecoration(labelText: 'Client *'),
                items: widget.clients
                    .map((c) => DropdownMenuItem<Object>(
                        value: c['id'],
                        child: Text('${c['raison_sociale']}',
                            overflow: TextOverflow.ellipsis)))
                    .toList(),
                onChanged: (v) => setState(() => clientId = v),
              ),
              const SizedBox(height: 12),
              if (showNewType)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                        controller: newTypeName,
                        decoration:
                            const InputDecoration(labelText: 'Nom du type')),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: newTypeExpires,
                      onChanged: (v) =>
                          setState(() => newTypeExpires = v ?? false),
                      title: const Text('A une date d\'expiration'),
                    ),
                    OutlinedButton(
                        onPressed: creatingType ? null : createType,
                        child: Text(
                            creatingType ? 'Création...' : 'Créer ce type')),
                  ],
                )
              else
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<Object>(
                        value: typeId,
                        decoration: const InputDecoration(
                            labelText: 'Type de document *'),
                        items: widget.documentTypes
                            .map((t) => DropdownMenuItem<Object>(
                                value: t['id'],
                                child: Text('${t['name']}',
                                    overflow: TextOverflow.ellipsis)))
                            .toList(),
                        onChanged: (v) => setState(() => typeId = v),
                      ),
                    ),
                    IconButton(
                        onPressed: () => setState(() => showNewType = true),
                        icon: const Icon(Icons.add_circle_outline,
                            color: AppColors.primaryGold)),
                  ],
                ),
              const SizedBox(height: 12),
              TextField(
                controller: expiration,
                readOnly: true,
                onTap: () async {
                  final picked = await showDatePicker(
                      context: context,
                      initialDate: DateTime.now(),
                      firstDate: DateTime(2020),
                      lastDate: DateTime(2100));
                  if (picked != null)
                    expiration.text = picked.toIso8601String().substring(0, 10);
                },
                decoration: const InputDecoration(
                    labelText: "Date d'expiration (optionnel)",
                    prefixIcon: Icon(Icons.calendar_today_outlined, size: 18)),
              ),
              const SizedBox(height: 18),
              PremiumButton(
                  onPressed: upload,
                  loading: uploading,
                  icon: Icons.upload_file_outlined,
                  label: uploading ? 'Import...' : 'Importer'),
            ],
          ),
        ),
      ),
    );
  }
}
