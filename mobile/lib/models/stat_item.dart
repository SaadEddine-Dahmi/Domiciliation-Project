import 'package:flutter/material.dart';

class StatItem {
  StatItem(this.label, this.value, this.icon, {this.onTap});

  final String label;
  final Object value;
  final IconData icon;
  final VoidCallback? onTap;
}
