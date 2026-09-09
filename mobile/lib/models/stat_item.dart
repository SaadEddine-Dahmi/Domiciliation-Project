// lib/models/stat_item.dart
//
// Plain data holder for a single dashboard stat tile: label, value, icon,
// and an optional tap handler to navigate to the relevant section.

import 'package:flutter/material.dart';

class StatItem {
  const StatItem(this.label, this.value, this.icon, {this.onTap});

  final String label;
  final Object value;
  final IconData icon;
  final VoidCallback? onTap;
}