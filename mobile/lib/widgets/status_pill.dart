// lib/widgets/status_pill.dart
//
// Small rounded status badge (Actif / Suspendu / Payé / En retard, etc).
// Color is resolved from a lookup table so every screen renders the same
// status with the same color instead of each screen inventing its own
// switch statement. Handles both French labels used in the UI and the
// English enum values stored on the backend (draft, active, expired...).
// Unknown statuses fall back to a neutral gray pill rather than crashing.

import 'package:flutter/material.dart';
import '../theme/app_design.dart';

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.status, this.compact = false});

  final Object? status;
  final bool compact;

  static const Map<String, Color> _colors = {
    'actif': AppColors.green,
    'active': AppColors.green,
    'payé': AppColors.green,
    'payee': AppColors.green,
    'paid': AppColors.green,
    'signé': AppColors.green,
    'signe': AppColors.green,
    'en attente': AppColors.amber,
    'pending': AppColors.amber,
    'suspendu': AppColors.amber,
    'brouillon': AppColors.amber,
    'draft': AppColors.amber,
    'nouveau': AppColors.amber,
    'inactif': AppColors.red,
    'expiré': AppColors.red,
    'expire': AppColors.red,
    'expired': AppColors.red,
    'en retard': AppColors.red,
    'rejeté': AppColors.red,
    'rejete': AppColors.red,
    'annulée': AppColors.red,
    'cancelled': AppColors.red,
    'terminated': AppColors.faint,
    'résilié': AppColors.faint,
    'archived': AppColors.faint,
  };

  static const Map<String, String> _labels = {
    'active': 'Actif',
    'draft': 'Brouillon',
    'expired': 'Expiré',
    'terminated': 'Résilié',
    'paid': 'Payée',
    'pending': 'En attente',
    'cancelled': 'Annulée',
  };

  Color _colorFor(String key) => _colors[key] ?? AppColors.faint;

  String _labelFor(String raw, String key) => _labels[key] ?? raw;

  @override
  Widget build(BuildContext context) {
    final raw = '${status ?? ''}'.trim();
    final key = raw.toLowerCase();
    final color = _colorFor(key);
    final label = _labelFor(raw.isEmpty ? '-' : raw, key);

    return Container(
      padding: EdgeInsets.symmetric(horizontal: compact ? 8 : 12, vertical: compact ? 3 : 5),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withOpacity(0.35)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w800,
          fontSize: compact ? 11 : 12.5,
        ),
      ),
    );
  }
}