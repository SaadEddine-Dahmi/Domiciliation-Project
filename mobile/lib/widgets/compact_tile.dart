import 'package:flutter/material.dart';

class CompactTile extends StatelessWidget {
  const CompactTile({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    this.trailing = '',
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final statusColor = switch (trailing.toLowerCase()) {
      'active' || 'actif' || 'paid' || 'payee' => const Color(0xff22c55e),
      'draft' || 'brouillon' || 'pending' || 'en attente' => const Color(0xfff5a623),
      'expired' || 'rejected' || 'terminated' || 'rejete' => const Color(0xffef4444),
      'archived' || 'archive' => const Color(0xff94a3b8),
      _ => const Color(0xff94a3b8),
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: scheme.primaryContainer.withOpacity(0.55),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: const Color(0xffc8a96e), size: 21),
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
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xaaf8f4ea), fontSize: 12),
                    ),
                  ],
                ),
              ),
              if (trailing.isNotEmpty) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    trailing,
                    style: TextStyle(fontSize: 11, color: statusColor, fontWeight: FontWeight.w900),
                    textAlign: TextAlign.end,
                  ),
                ),
              ],
              if (onTap != null) const Icon(Icons.chevron_right, size: 18, color: Color(0x88f8f4ea)),
            ],
          ),
        ),
      ),
    );
  }
}
