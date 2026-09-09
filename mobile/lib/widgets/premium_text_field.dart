// lib/widgets/premium_text_field.dart
//
// Styled text input for auth screens (email, password, etc). Wraps the
// themed InputDecoration with a leading icon and, for obscured fields, a
// show/hide-password toggle built into the field itself.

import 'package:flutter/material.dart';
import '../theme/app_design.dart';

class PremiumTextField extends StatefulWidget {
  const PremiumTextField({
    super.key,
    required this.controller,
    required this.label,
    this.icon,
    this.keyboardType,
    this.obscureText = false,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final IconData? icon;
  final TextInputType? keyboardType;
  final bool obscureText;
  final ValueChanged<String>? onSubmitted;

  @override
  State<PremiumTextField> createState() => _PremiumTextFieldState();
}

class _PremiumTextFieldState extends State<PremiumTextField> {
  late bool hidden = widget.obscureText;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: widget.controller,
      keyboardType: widget.keyboardType,
      obscureText: hidden,
      onSubmitted: widget.onSubmitted,
      style: const TextStyle(color: AppColors.text),
      decoration: InputDecoration(
        labelText: widget.label,
        prefixIcon: widget.icon == null
            ? null
            : Icon(widget.icon, color: AppColors.muted, size: 20),
        suffixIcon: widget.obscureText
            ? IconButton(
                icon: Icon(
                  hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  color: AppColors.muted,
                  size: 20,
                ),
                onPressed: () => setState(() => hidden = !hidden),
              )
            : null,
      ),
    );
  }
}