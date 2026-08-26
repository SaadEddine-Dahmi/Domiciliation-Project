import 'package:flutter/material.dart';

import '../theme/app_design.dart';

class GoldAvatar extends StatelessWidget {
  const GoldAvatar({super.key, required this.label, this.radius = 24});

  final String label;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return CircleAvatar(
      radius: radius,
      backgroundColor: AppColors.primaryGold,
      child: Text(
        initialsFrom(label),
        style: TextStyle(
          color: AppColors.background,
          fontFamily: 'Fraunces',
          fontSize: radius * 0.72,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
