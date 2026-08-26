import 'package:flutter/material.dart';

import 'api_future.dart';

class ResourceList extends StatelessWidget {
  const ResourceList({
    super.key,
    required this.load,
    required this.itemBuilder,
    required this.emptyTitle,
  });

  final Future<List<dynamic>> Function() load;
  final Widget Function(dynamic item) itemBuilder;
  final String emptyTitle;

  @override
  Widget build(BuildContext context) {
    return ApiFuture<List<dynamic>>(
      load: load,
      builder: (context, items, refresh) => RefreshIndicator(
        onRefresh: refresh,
        child: items.isEmpty
            ? ListView(
                padding: const EdgeInsets.all(24),
                children: [
                  const SizedBox(height: 120),
                  Icon(Icons.inbox_outlined, size: 48, color: Colors.grey.shade500),
                  const SizedBox(height: 12),
                  Text(emptyTitle, textAlign: TextAlign.center),
                ],
              )
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) => itemBuilder(items[index]),
              ),
      ),
    );
  }
}
