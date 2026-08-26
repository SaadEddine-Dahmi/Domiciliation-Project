import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../widgets/compact_tile.dart';
import '../widgets/resource_list.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: ResourceList(
        load: () => api.list('/api/notifications'),
        emptyTitle: 'Aucune notification',
        itemBuilder: (item) => CompactTile(
          icon: Icons.notifications_outlined,
          title: '${item['subject'] ?? 'Notification'}',
          subtitle: '${item['message'] ?? ''}',
          trailing: item['is_read'] == true ? 'Lu' : 'Nouveau',
        ),
      ),
    );
  }
}
