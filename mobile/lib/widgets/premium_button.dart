// lib/widgets/premium_button.dart
//
// Full-width action button used on auth screens and confirmation flows.
// Two visual variants:
//   filled   (default) — solid gold background, primary action
//   outlined            — bordered, secondary action
// Shows a small spinner in place of the icon while `loading` is true and
// disables the tap target so the action can't be double-submitted.

import 'package:flutter/material.dart';
import '../theme/app_design.dart';

class PremiumButton extends StatelessWidget {
  const PremiumButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.outlined = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final bool outlined;

  @override
  Widget build(BuildContext context) {
    final child = Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (loading)
          SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: outlined ? AppColors.primaryGold : AppColors.background,
            ),
          )
        else if (icon != null)
          Icon(icon, size: 19),
        if (loading || icon != null) const SizedBox(width: 10),
        Text(label),
      ],
    );

    if (outlined) {
      return SizedBox(
        width: double.infinity,
        child: OutlinedButton(
          onPressed: loading ? null : onPressed,
          child: child,
        ),
      );
    }

    return SizedBox(
      width: double.infinity,
      child: FilledButton(
        onPressed: loading ? null : onPressed,
        child: child,
      ),
    );
  }
}