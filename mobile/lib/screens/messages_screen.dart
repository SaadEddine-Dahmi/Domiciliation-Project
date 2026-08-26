import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/gold_avatar.dart';
import '../widgets/premium_card.dart';
import '../widgets/status_pill.dart';

class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  List<dynamic> messages = [];
  List<dynamic> clients = [];
  bool loading = true;
  bool clientsLoading = false;
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
      final loaded = await widget.api.list('/api/messages').timeout(const Duration(seconds: 20));
      if (!mounted) return;
      setState(() {
        messages = loaded;
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

  Future<void> loadClientsForCompose() async {
    if (clients.isNotEmpty || clientsLoading) return;
    setState(() => clientsLoading = true);
    try {
      final loaded = await widget.api.list('/api/clients').timeout(const Duration(seconds: 20));
      if (mounted) setState(() => clients = loaded);
    } catch (e) {
      showError(e);
    } finally {
      if (mounted) setState(() => clientsLoading = false);
    }
  }

  void maybePop(DragEndDetails details) {
    final velocity = details.primaryVelocity ?? 0;
    if (velocity.abs() > 450 && Navigator.canPop(context)) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.translucent,
      onHorizontalDragEnd: maybePop,
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back),
            onPressed: () {
              if (Navigator.canPop(context)) Navigator.pop(context);
            },
          ),
          title: const Text('Messagerie'),
          actions: [
            IconButton(
              tooltip: 'Actualiser',
              onPressed: loading ? null : load,
              icon: const Icon(Icons.refresh),
            ),
            IconButton(
              tooltip: 'Composer',
              onPressed: () => openCompose(),
              icon: const Icon(Icons.edit),
            ),
          ],
        ),
        body: RefreshIndicator(
          onRefresh: load,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Messages clients', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontSize: 30)),
                        const SizedBox(height: 6),
                        Text('${messages.length} message(s)', style: const TextStyle(color: AppColors.muted)),
                      ],
                    ),
                  ),
                  FilledButton.icon(
                    onPressed: () => openCompose(),
                    icon: const Icon(Icons.edit),
                    label: const Text('Composer'),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              if (loading)
                const _MessagesLoading()
              else if (error != null)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ErrorBanner(message: error!),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(onPressed: load, icon: const Icon(Icons.refresh), label: const Text('Reessayer')),
                  ],
                )
              else if (messages.isEmpty)
                PremiumCard(
                  child: Column(
                    children: [
                      const Icon(Icons.inbox_outlined, size: 42, color: AppColors.primaryGold),
                      const SizedBox(height: 12),
                      const Text('Aucun message pour le moment.', style: TextStyle(fontWeight: FontWeight.w900)),
                      const SizedBox(height: 12),
                      FilledButton.icon(onPressed: () => openCompose(), icon: const Icon(Icons.edit), label: const Text('Composer un message')),
                    ],
                  ),
                )
              else
                ...messages.map(
                  (message) => _MessageTile(
                    message: message,
                    onTap: () => openMessage(message),
                    onReceipt: message['is_outgoing'] == true ? () => refreshReceipt(message) : null,
                  ),
                ),
              const SizedBox(height: 80),
            ],
          ),
        ),
        floatingActionButton: FloatingActionButton(
          backgroundColor: AppColors.primaryGold,
          foregroundColor: AppColors.background,
          onPressed: () => openCompose(),
          child: const Icon(Icons.edit),
        ),
      ),
    );
  }

  Future<void> openCompose() async {
    await loadClientsForCompose();
    if (!mounted) return;
    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => ComposeMessageSheet(api: widget.api, clients: clients, clientsLoading: clientsLoading),
    );
    if (sent == true) await load();
  }

  Future<void> openMessage(dynamic message) async {
    if (message['is_outgoing'] != true && message['is_read'] != true) {
      try {
        await widget.api.markMessageRead(message['id']);
        message['is_read'] = true;
        if (mounted) setState(() {});
      } catch (_) {}
    }

    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (_) => _MessageDetails(message: message),
    );
  }

  Future<void> refreshReceipt(dynamic message) async {
    try {
      final receipt = await widget.api.messageReceipt(message['id']);
      setState(() {
        message['is_read'] = receipt['is_read'];
        message['read_at'] = receipt['read_at'];
      });
    } catch (e) {
      showError(e);
    }
  }

  void showError(Object e) {
    final message = e is ApiException ? e.message : e.toString();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class ComposeMessageSheet extends StatefulWidget {
  const ComposeMessageSheet({
    super.key,
    required this.api,
    required this.clients,
    required this.clientsLoading,
  });

  final ApiClient api;
  final List<dynamic> clients;
  final bool clientsLoading;

  @override
  State<ComposeMessageSheet> createState() => _ComposeMessageSheetState();
}

class _ComposeMessageSheetState extends State<ComposeMessageSheet> {
  final subject = TextEditingController();
  final message = TextEditingController();
  Object? selectedClientUserId;
  bool sending = false;
  String? error;

  @override
  void dispose() {
    subject.dispose();
    message.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (selectedClientUserId == null) {
      setState(() => error = 'Selectionnez un client.');
      return;
    }
    if (message.text.trim().isEmpty) {
      setState(() => error = 'Le message est obligatoire.');
      return;
    }

    setState(() {
      sending = true;
      error = null;
    });

    try {
      await widget.api.sendMessage(
        clientUserId: selectedClientUserId!,
        subject: subject.text.trim(),
        message: message.text.trim(),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final availableClients = widget.clients.where((client) {
      return client['client_user_id'] != null || client['clientUser'] != null || client['client_user'] != null;
    }).toList();

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(context).viewInsets.bottom + 20),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text('Nouveau message', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 14),
            if (error != null) ...[
              ErrorBanner(message: error!),
              const SizedBox(height: 12),
            ],
            DropdownButtonFormField<Object>(
              value: selectedClientUserId,
              decoration: const InputDecoration(labelText: 'Destinataire *', prefixIcon: Icon(Icons.business_outlined)),
              items: availableClients.map((client) {
                final user = client['clientUser'] ?? client['client_user'];
                final Object userId = client['client_user_id'] ?? user['id'];
                final name = '${client['raison_sociale'] ?? 'Client'}';
                return DropdownMenuItem<Object>(value: userId, child: Text(name, overflow: TextOverflow.ellipsis));
              }).toList(),
              onChanged: sending ? null : (value) => setState(() => selectedClientUserId = value),
            ),
            if (widget.clientsLoading)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text('Chargement des clients...', style: TextStyle(color: AppColors.muted, fontSize: 12)),
              ),
            if (!widget.clientsLoading && availableClients.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text('Aucun client avec portail actif.', style: TextStyle(color: AppColors.muted, fontSize: 12)),
              ),
            const SizedBox(height: 12),
            TextField(controller: subject, decoration: const InputDecoration(labelText: 'Objet')),
            const SizedBox(height: 12),
            TextField(
              controller: message,
              minLines: 5,
              maxLines: 8,
              maxLength: 2000,
              decoration: const InputDecoration(labelText: 'Message *'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: OutlinedButton(onPressed: sending ? null : () => Navigator.pop(context, false), child: const Text('Annuler'))),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: sending ? null : send,
                    icon: sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send),
                    label: Text(sending ? 'Envoi...' : 'Envoyer'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MessageTile extends StatelessWidget {
  const _MessageTile({required this.message, required this.onTap, this.onReceipt});

  final dynamic message;
  final VoidCallback onTap;
  final VoidCallback? onReceipt;

  @override
  Widget build(BuildContext context) {
    final outgoing = message['is_outgoing'] == true;
    final person = outgoing ? message['receiver'] : message['sender'];
    final name = displayName(person, fallback: outgoing ? 'Client' : 'Domiciliataire');
    final subject = '${message['subject'] ?? ''}'.trim();
    final body = '${message['message'] ?? ''}'.trim();

    return PremiumCard(
      margin: const EdgeInsets.only(bottom: 12),
      onTap: onTap,
      child: Row(
        children: [
          GoldAvatar(label: name),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(subject.isNotEmpty ? subject : name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900)),
                const SizedBox(height: 5),
                Text(body, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted)),
                const SizedBox(height: 6),
                Text(formatDate('${message['created_at'] ?? ''}'), style: const TextStyle(color: AppColors.soft, fontSize: 11)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            children: [
              StatusPill(status: message['is_read'] == true ? 'Lu' : outgoing ? 'Envoye' : 'Non lu', compact: true),
              if (onReceipt != null)
                IconButton(
                  tooltip: 'Verifier la lecture',
                  onPressed: onReceipt,
                  icon: const Icon(Icons.refresh, size: 18),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MessageDetails extends StatelessWidget {
  const _MessageDetails({required this.message});

  final dynamic message;

  @override
  Widget build(BuildContext context) {
    final outgoing = message['is_outgoing'] == true;
    final person = outgoing ? message['receiver'] : message['sender'];
    final name = displayName(person, fallback: outgoing ? 'Client' : 'Domiciliataire');

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 22),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                GoldAvatar(label: name),
                const SizedBox(width: 12),
                Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17))),
                StatusPill(status: message['is_read'] == true ? 'Lu' : 'Non lu', compact: true),
              ],
            ),
            const SizedBox(height: 18),
            Text('${message['subject'] ?? 'Message'}', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            Text(formatDate('${message['created_at'] ?? ''}'), style: const TextStyle(color: AppColors.soft)),
            const Divider(height: 26),
            Text('${message['message'] ?? ''}', style: const TextStyle(height: 1.55, color: AppColors.text)),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer')),
            ),
          ],
        ),
      ),
    );
  }
}

class _MessagesLoading extends StatelessWidget {
  const _MessagesLoading();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List.generate(
        4,
        (index) => PremiumCard(
          margin: const EdgeInsets.only(bottom: 12),
          child: Row(
            children: [
              Container(width: 44, height: 44, decoration: const BoxDecoration(color: AppColors.surfaceRaised, shape: BoxShape.circle)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(height: 12, width: 180, color: AppColors.surfaceRaised),
                    const SizedBox(height: 8),
                    Container(height: 10, width: double.infinity, color: AppColors.surfaceRaised),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

String displayName(dynamic user, {required String fallback}) {
  if (user is! Map) return fallback;
  final full = '${user['nom'] ?? ''} ${user['prenom'] ?? ''}'.trim();
  if (full.isNotEmpty) return full;
  return '${user['email'] ?? fallback}';
}

String formatDate(String value) {
  if (value.isEmpty) return '-';
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  final local = parsed.toLocal();
  String two(int number) => number.toString().padLeft(2, '0');
  return '${two(local.day)}/${two(local.month)}/${local.year} ${two(local.hour)}:${two(local.minute)}';
}
