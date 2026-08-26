import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../widgets/compact_tile.dart';
import '../widgets/resource_list.dart';

class AdminDomiciliatairesScreen extends StatelessWidget {
  const AdminDomiciliatairesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return ResourceList(
      load: () => api.list('/api/admin/domiciliataires'),
      emptyTitle: 'Aucun domiciliataire',
      itemBuilder: (item) {
        final name = '${item['nom'] ?? ''} ${item['prenom'] ?? ''}'.trim();
        return CompactTile(
          icon: Icons.account_balance_outlined,
          title: name.isEmpty ? '${item['email'] ?? 'Domiciliataire'}' : name,
          subtitle: '${item['email'] ?? ''}',
          trailing: '${item['status'] ?? ''}',
        );
      },
    );
  }
}
