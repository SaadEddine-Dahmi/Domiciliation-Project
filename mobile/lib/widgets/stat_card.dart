import 'package:flutter/material.dart';

import '../models/stat_item.dart';

class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.item});

  final StatItem item;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: item.onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: const Color(0x1fc8a96e),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(item.icon, color: const Color(0xffc8a96e), size: 20),
              ),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  '${item.value}',
                  maxLines: 1,
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    color: Color(0xffc8a96e),
                    fontFamily: 'Fraunces',
                  ),
                ),
              ),
              Row(
                children: [
                  Expanded(
                    child: Text(item.label, style: const TextStyle(color: Color(0xaaf8f4ea), fontWeight: FontWeight.w700)),
                  ),
                  if (item.onTap != null) const Icon(Icons.chevron_right, size: 18, color: Color(0x88f8f4ea)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
