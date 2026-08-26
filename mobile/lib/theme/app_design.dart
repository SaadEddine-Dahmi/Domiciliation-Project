import 'package:flutter/material.dart';

class AppColors {
  static const background = Color(0xff0d0f1a);
  static const surface = Color(0xff13161f);
  static const surfaceRaised = Color(0xff171b26);
  static const border = Color(0x0fffffff);
  static const primaryGold = Color(0xffc8a96e);
  static const green = Color(0xff22c55e);
  static const yellow = Color(0xfff5a623);
  static const red = Color(0xffef4444);
  static const text = Color(0xfff8f4ea);
  static const muted = Color(0xa8f8f4ea);
  static const soft = Color(0x66f8f4ea);
}

class AppSpacing {
  static const page = 20.0;
  static const radius = 18.0;
  static const desktopBreakpoint = 900.0;
}

bool isDesktop(BuildContext context) {
  return MediaQuery.sizeOf(context).width >= AppSpacing.desktopBreakpoint;
}

Color statusColor(String? status) {
  final normalized = (status ?? '').toLowerCase();
  if (normalized.contains('actif') || normalized.contains('pay') || normalized.contains('sign')) {
    return AppColors.green;
  }
  if (normalized.contains('attente') || normalized.contains('draft') || normalized.contains('brouillon') || normalized.contains('suspend')) {
    return AppColors.yellow;
  }
  if (normalized.contains('expire') || normalized.contains('retard') || normalized.contains('reject') || normalized.contains('rejete')) {
    return AppColors.red;
  }
  return AppColors.soft;
}

String statusLabel(Object? status) {
  final value = '${status ?? ''}'.trim();
  if (value.isEmpty) return 'Archive';
  return value[0].toUpperCase() + value.substring(1).replaceAll('_', ' ');
}

String initialsFrom(String value) {
  final words = value.trim().split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();
  if (words.isEmpty) return 'U';
  if (words.length == 1) return words.first.substring(0, words.first.length >= 2 ? 2 : 1).toUpperCase();
  return words.take(2).map((word) => word[0]).join().toUpperCase();
}
