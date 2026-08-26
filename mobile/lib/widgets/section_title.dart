import 'package:flutter/material.dart';

class SectionTitle extends StatelessWidget {
  const SectionTitle({super.key, required this.title, required this.onRefresh});

  final String title;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        ),
        IconButton(onPressed: onRefresh, icon: const Icon(Icons.refresh)),
      ],
    );
  }
}
