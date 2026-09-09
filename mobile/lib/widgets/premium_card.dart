// lib/widgets/premium_card.dart
//
// The base card shell used everywhere: dashboard panels, list rows, form
// sections. Wraps its child in the shared surface color, border and corner
// radius so every card in the app looks identical without repeating a
// BoxDecoration in each screen. Pass `onTap` to make the whole card
// tappable with a ripple; omit it for static content cards.

import 'package:flutter/material.dart';
import '../theme/app_design.dart';

class PremiumCard extends StatelessWidget {
  const PremiumCard({
    super.key,
    required this.child,
    this.margin,
    this.padding,
    this.onTap,
  });

  final Widget child;
  final EdgeInsetsGeometry? margin;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Container(
      width: double.infinity,
      margin: margin,
      padding: padding ?? const EdgeInsets.all(AppSpacing.cardPadding),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        border: Border.all(color: AppColors.border),
      ),
      child: child,
    );

    if (onTap == null) return content;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        child: content,
      ),
    );
  }
}