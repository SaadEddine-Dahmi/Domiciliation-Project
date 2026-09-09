// lib/widgets/stat_card.dart
//
// Single tile in the dashboard's horizontal stat strip (Clients, Contrats
// actifs, Brouillons, CA du mois...). The value is rendered in the serif
// display font to match the mockup's treatment of headline numbers.

import 'package:flutter/material.dart';
import '../models/stat_item.dart';
import '../theme/app_design.dart';

class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.item});

  final StatItem item;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
      child: InkWell(
        onTap: item.onTap,
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: AppColors.primaryGold.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(item.icon, size: 18, color: AppColors.primaryGold),
              ),
              const Spacer(),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  '${item.value}',
                  style: const TextStyle(
                    fontFamily: 'Fraunces',
                    fontWeight: FontWeight.w700,
                    fontSize: 22,
                    color: AppColors.text,
                  ),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.muted, fontSize: 12, fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      ),
    );
  }
}