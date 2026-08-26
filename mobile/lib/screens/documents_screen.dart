import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/compact_tile.dart';
import '../widgets/error_banner.dart';
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
  PlatformFile? selectedFile;
  Object? selectedClientId;
  Object? selectedTypeId;
  final expiration = TextEditingController();
  final newTypeName = TextEditingController();
  bool newTypeExpires = false;
  bool showNewType = false;
  bool loading = true;
  bool uploading = false;
  bool creatingType = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    expiration.dispose();
    newTypeName.dispose();
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
        selectedClientId ??= loadedClients.isEmpty ? null : loadedClients.first['id'];
        selectedTypeId ??= loadedTypes.isEmpty ? null : loadedTypes.first['id'];
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

  Future<void> pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    final file = result?.files.single;
    if (file == null) return;
    setState(() => selectedFile = file);
  }

  Future<void> pickExpirationDate() async {
    final current = DateTime.tryParse(expiration.text.trim()) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) expiration.text = picked.toIso8601String().substring(0, 10);
  }

  Future<void> createType() async {
    final name = newTypeName.text.trim();
    if (name.isEmpty) {
      setState(() => error = 'Renseignez le nom du type de document.');
      return;
    }
    setState(() {
      creatingType = true;
      error = null;
    });
    try {
      final created = await widget.api.createDocumentType({
        'name': name,
        'has_expiration': newTypeExpires,
        'is_required': false,
      });
      if (!mounted) return;
      setState(() {
        documentTypes = [...documentTypes, created];
        selectedTypeId = created['id'];
        showNewType = false;
        newTypeExpires = false;
        newTypeName.clear();
      });
      showSnack('Type de document cree');
    } catch (e) {
      if (!mounted) return;
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => creatingType = false);
    }
  }

  Future<void> upload() async {
    if (selectedFile == null) {
      setState(() => error = 'Selectionnez un fichier.');
      return;
    }
    if (selectedClientId == null) {
      setState(() => error = 'Selectionnez un client.');
      return;
    }
    if (selectedTypeId == null) {
      setState(() => error = 'Selectionnez un type de document.');
      return;
    }

    setState(() {
      uploading = true;
      error = null;
    });
    try {
      await widget.api.uploadDocument(
        entrepriseId: selectedClientId!,
        documentTypeId: selectedTypeId!,
        file: selectedFile!,
        dateExpiration: expiration.text,
      );
      if (!mounted) return;
      setState(() {
        selectedFile = null;
        expiration.clear();
      });
      await load();
      showSnack('Document importe et associe au client');
    } catch (e) {
      if (!mounted) return;
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => uploading = false);
    }
  }

  void showSnack(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
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
          _UploadCard(
            selectedFile: selectedFile,
            clients: clients,
            documentTypes: documentTypes,
            selectedClientId: selectedClientId,
            selectedTypeId: selectedTypeId,
            expiration: expiration,
            showNewType: showNewType,
            newTypeName: newTypeName,
            newTypeExpires: newTypeExpires,
            uploading: uploading,
            creatingType: creatingType,
            onPickFile: pickFile,
            onClearFile: () => setState(() => selectedFile = null),
            onClientChanged: (value) => setState(() => selectedClientId = value),
            onTypeChanged: (value) => setState(() => selectedTypeId = value),
            onPickExpiration: pickExpirationDate,
            onToggleNewType: () => setState(() => showNewType = !showNewType),
            onNewTypeExpiresChanged: (value) => setState(() => newTypeExpires = value),
            onCreateType: createType,
            onUpload: upload,
          ),
          const SizedBox(height: 18),
          Text('Documents importes', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          if (documents.isEmpty)
            const PremiumCard(child: Text('Aucun document', style: TextStyle(color: AppColors.muted)))
          else
            for (final item in documents)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: CompactTile(
                  icon: item['is_pdf'] == true ? Icons.picture_as_pdf_outlined : Icons.insert_drive_file_outlined,
                  title: '${item['name'] ?? item['document_type']?['name'] ?? 'Document'}',
                  subtitle: '${item['entreprise']?['raison_sociale'] ?? item['extension'] ?? ''}',
                  trailing: item['date_expiration'] == null ? '' : 'Expire ${item['date_expiration']}',
                  onTap: () => openExternal(widget.api.documentPreviewUrl(item['id'])),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _UploadCard extends StatelessWidget {
  const _UploadCard({
    required this.selectedFile,
    required this.clients,
    required this.documentTypes,
    required this.selectedClientId,
    required this.selectedTypeId,
    required this.expiration,
    required this.showNewType,
    required this.newTypeName,
    required this.newTypeExpires,
    required this.uploading,
    required this.creatingType,
    required this.onPickFile,
    required this.onClearFile,
    required this.onClientChanged,
    required this.onTypeChanged,
    required this.onPickExpiration,
    required this.onToggleNewType,
    required this.onNewTypeExpiresChanged,
    required this.onCreateType,
    required this.onUpload,
  });

  final PlatformFile? selectedFile;
  final List<dynamic> clients;
  final List<dynamic> documentTypes;
  final Object? selectedClientId;
  final Object? selectedTypeId;
  final TextEditingController expiration;
  final bool showNewType;
  final TextEditingController newTypeName;
  final bool newTypeExpires;
  final bool uploading;
  final bool creatingType;
  final VoidCallback onPickFile;
  final VoidCallback onClearFile;
  final ValueChanged<Object?> onClientChanged;
  final ValueChanged<Object?> onTypeChanged;
  final VoidCallback onPickExpiration;
  final VoidCallback onToggleNewType;
  final ValueChanged<bool> onNewTypeExpiresChanged;
  final VoidCallback onCreateType;
  final VoidCallback onUpload;

  @override
  Widget build(BuildContext context) {
    final file = selectedFile;
    final canUpload = file != null && selectedClientId != null && selectedTypeId != null && !uploading;

    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Icon(Icons.document_scanner_outlined, color: AppColors.primaryGold),
              const SizedBox(width: 10),
              Text('Scanner / Importer', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
            ],
          ),
          const SizedBox(height: 16),
          Material(
            color: Colors.transparent,
            borderRadius: BorderRadius.circular(18),
            child: InkWell(
              onTap: onPickFile,
              borderRadius: BorderRadius.circular(18),
              child: Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: file == null ? AppColors.surfaceRaised : AppColors.green.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: file == null ? AppColors.border : AppColors.green.withOpacity(0.45)),
                ),
                child: Column(
                  children: [
                    Icon(file == null ? Icons.upload_file_outlined : Icons.task_outlined, color: file == null ? AppColors.primaryGold : AppColors.green, size: 36),
                    const SizedBox(height: 8),
                    Text(file == null ? 'Selectionner un fichier' : file.name, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(file == null ? 'PDF, JPG, PNG - max 10MB' : '${(file.size / 1024).toStringAsFixed(0)} KB', style: const TextStyle(color: AppColors.muted)),
                  ],
                ),
              ),
            ),
          ),
          if (file != null) ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(onPressed: onClearFile, icon: const Icon(Icons.close), label: const Text('Supprimer le fichier')),
          ],
          const SizedBox(height: 16),
          DropdownButtonFormField<Object>(
            isExpanded: true,
            value: selectedClientId,
            decoration: const InputDecoration(labelText: 'Entreprise cliente *', prefixIcon: Icon(Icons.business_outlined)),
            items: clients
                .map((client) => DropdownMenuItem<Object>(
                      value: client['id'],
                      child: Text('${client['raison_sociale'] ?? 'Client'}', overflow: TextOverflow.ellipsis),
                    ))
                .toList(),
            onChanged: onClientChanged,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              const Expanded(child: Text('Type de document *', style: TextStyle(color: AppColors.muted))),
              TextButton.icon(
                onPressed: onToggleNewType,
                icon: Icon(showNewType ? Icons.close : Icons.add),
                label: Text(showNewType ? 'Annuler' : 'Nouveau type'),
              ),
            ],
          ),
          if (showNewType)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.primaryGold.withOpacity(0.08),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.primaryGold.withOpacity(0.25)),
              ),
              child: Column(
                children: [
                  TextField(controller: newTypeName, decoration: const InputDecoration(labelText: 'Nom du type')),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: newTypeExpires,
                    onChanged: (value) => onNewTypeExpiresChanged(value ?? false),
                    title: const Text('Ce type a une date expiration'),
                  ),
                  FilledButton.icon(
                    onPressed: creatingType ? null : onCreateType,
                    icon: const Icon(Icons.add),
                    label: Text(creatingType ? 'Creation...' : 'Creer ce type'),
                  ),
                ],
              ),
            )
          else
            DropdownButtonFormField<Object>(
              isExpanded: true,
              value: selectedTypeId,
              decoration: const InputDecoration(prefixIcon: Icon(Icons.category_outlined)),
              items: documentTypes
                  .map((type) => DropdownMenuItem<Object>(
                        value: type['id'],
                        child: Text('${type['name'] ?? 'Type'}${type['has_expiration'] == true ? ' (expiration)' : ''}', overflow: TextOverflow.ellipsis),
                      ))
                  .toList(),
              onChanged: onTypeChanged,
            ),
          const SizedBox(height: 12),
          TextField(
            controller: expiration,
            readOnly: true,
            onTap: onPickExpiration,
            decoration: const InputDecoration(
              labelText: 'Date expiration (optionnel)',
              prefixIcon: Icon(Icons.calendar_today_outlined),
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: canUpload ? onUpload : null,
            icon: Icon(uploading ? Icons.hourglass_top : Icons.upload_file_outlined),
            label: Text(uploading ? 'Import en cours...' : 'Importer et associer'),
          ),
        ],
      ),
    );
  }
}
