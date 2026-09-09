// lib/widgets/gold_avatar.dart
//
// Circular initials avatar (up to 2 letters, gold text on translucent gold
// background) used for clients, domiciliataires and contacts throughout
// the app — matches the "SO" / "IN" / "MG" avatars in the client list.

import 'package:flutter/material.dart';
import '../theme/app_design.dart';

class GoldAvatar extends StatelessWidget {
  const GoldAvatar({super.key, required this.label, this.radius = 20});

  final String label;
  final double radius;

  String get _initials {
    final trimmed = label.trim();
    if (trimmed.isEmpty) return '?';
    final parts = trimmed.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts[0].substring(0, 1) + parts[1].substring(0, 1)).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return CircleAvatar(
      radius: radius,
      backgroundColor: AppColors.primaryGold.withOpacity(0.16),
      child: Text(
        _initials,
        style: TextStyle(
          color: AppColors.primaryGold,
          fontWeight: FontWeight.w900,
          fontSize: radius * 0.62,
        ),
      ),
    );
  }
}