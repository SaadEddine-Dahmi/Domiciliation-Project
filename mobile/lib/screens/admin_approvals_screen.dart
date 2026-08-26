import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../widgets/api_future.dart';
import '../widgets/compact_tile.dart';
import '../widgets/error_banner.dart';

class AdminApprovalsScreen extends StatelessWidget {
  const AdminApprovalsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Approbations')),
      body: ApiFuture<List<dynamic>>(
        load: api.pendingDomiciliataires,
        builder: (context, users, refresh) {
          return RefreshIndicator(
            onRefresh: refresh,
            child: users.isEmpty
                ? ListView(
                    padding: const EdgeInsets.all(24),
                    children: const [
                      SizedBox(height: 120),
                      Icon(Icons.verified_user_outlined, size: 52, color: Color(0xff175840)),
                      SizedBox(height: 12),
                      Text('Aucune demande en attente', textAlign: TextAlign.center),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: users.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final user = users[index];
                      final name = '${user['nom'] ?? ''} ${user['prenom'] ?? ''}'.trim();
                      return CompactTile(
                        icon: Icons.person_add_alt_1_outlined,
                        title: name.isEmpty ? '${user['email']}' : name,
                        subtitle: '${user['email'] ?? ''}\n${user['telephone'] ?? ''}',
                        trailing: 'En attente',
                        onTap: () => showApprovalSheet(context, user, refresh),
                      );
                    },
                  ),
          );
        },
      ),
    );
  }

  Future<void> showApprovalSheet(
    BuildContext context,
    dynamic user,
    Future<void> Function() refresh,
  ) async {
    final activationDate = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
    final reason = TextEditingController();
    String? error;
    bool saving = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setState) {
            Future<void> run(Future<void> Function() action) async {
              setState(() {
                saving = true;
                error = null;
              });
              try {
                await action();
                if (context.mounted) Navigator.pop(context);
                await refresh();
                return;
              } catch (e) {
                setState(() => error = e is ApiException ? e.message : e.toString());
              } finally {
                if (context.mounted) setState(() => saving = false);
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${user['email']}', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 16),
                  TextField(
                    controller: activationDate,
                    decoration: const InputDecoration(
                      labelText: 'Date d’activation',
                      prefixIcon: Icon(Icons.event_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: reason,
                    minLines: 2,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Raison du rejet',
                      prefixIcon: Icon(Icons.subject),
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    ErrorBanner(message: error!),
                  ],
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: saving
                              ? null
                              : () => run(() => api.rejectDomiciliataire(user['id'], reason.text.trim())),
                          icon: const Icon(Icons.close),
                          label: const Text('Rejeter'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: saving
                              ? null
                              : () => run(() => api.approveDomiciliataire(user['id'], activationDate.text.trim())),
                          icon: const Icon(Icons.check),
                          label: Text(saving ? '...' : 'Approuver'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
