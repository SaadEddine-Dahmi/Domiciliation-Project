// lib/widgets/compact_tile.dart
//
// Standard list row: leading icon (or initials avatar via `avatarLabel`),
// title, subtitle, and a trailing status pill / chevron. Used for clients,
// documents, notifications, messages, contracts and invoices — anywhere
// the app renders a scrollable resource list.

import 'package:flutter/material.dart';
import '../theme/app_design.dart';
import 'gold_avatar.dart';
import 'status_pill.dart';

class CompactTile extends StatelessWidget {
  const CompactTile({
    super.key,
    this.icon,
    this.avatarLabel,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.showChevron = true,
  });

  /// Leading icon. Ignored when [avatarLabel] is provided.
  final IconData? icon;

  /// When set, renders a [GoldAvatar] with these initials instead of [icon].
  final String? avatarLabel;

  final String title;
  final String? subtitle;

  /// Rendered as a [StatusPill]. Pass null to hide the trailing badge.
  final String? trailing;

  final VoidCallback? onTap;
  final bool showChevron;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              if (avatarLabel != null)
                GoldAvatar(label: avatarLabel!, radius: 20)
              else
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: AppColors.primaryGold.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon ?? Icons.circle_outlined, color: AppColors.primaryGold, size: 19),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w800, fontSize: 14.5),
                    ),
                    if (subtitle != null && subtitle!.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        subtitle!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: AppColors.muted, fontSize: 12.5),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              if (trailing != null && trailing!.isNotEmpty) StatusPill(status: trailing, compact: true),
              if (showChevron && onTap != null) ...[
                const SizedBox(width: 6),
                const Icon(Icons.chevron_right, color: AppColors.faint, size: 20),
              ],
            ],
          ),
        ),
      ),
    );
  }
}