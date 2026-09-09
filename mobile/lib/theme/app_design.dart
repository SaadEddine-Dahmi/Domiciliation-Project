// lib/theme/app_design.dart
//
// Central design system: colors, spacing and the ThemeData consumed by
// MaterialApp. Every screen and shared widget pulls its visual tokens from
// here — never hardcode a hex color directly in a screen file.
//
// Palette reference (from the approved mobile mockups):
//   Background      #0B0D14  near-black navy
//   Surface         #12141F  cards / sheets
//   Surface raised  #171A26  nested rows, chips, input fields
//   Border          rgba(255,255,255,0.06)
//   Gold accent     #C8A96E  primary actions, highlights, active states
//   Text            #F5F1E8  primary text on dark background
//   Muted text      #9CA3AF  secondary / helper text
//   Green           #22C55E  success / paid / active
//   Amber           #F5A623  pending / suspended / warning
//   Red             #EF4444  error / expired / overdue
//
// Fonts: register 'Fraunces' (serif, headings + money amounts) and
// 'PlusJakartaSans' (sans, body/labels) in pubspec.yaml under flutter/fonts.

import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  static const Color background = Color(0xFF0B0D14);
  static const Color surface = Color(0xFF12141F);
  static const Color surfaceRaised = Color(0xFF171A26);
  static const Color border = Color(0x0FFFFFFF); // ~6% white
  static const Color borderStrong = Color(0x1FFFFFFF); // ~12% white

  static const Color primaryGold = Color(0xFFC8A96E);
  static const Color primaryGoldDark = Color(0xFFB08F52);

  static const Color text = Color(0xFFF5F1E8);
  static const Color muted = Color(0xFF9CA3AF);
  static const Color faint = Color(0xFF6B7280);
  static const Color soft = faint;

  static const Color green = Color(0xFF22C55E);
  static const Color amber = Color(0xFFF5A623);
  static const Color red = Color(0xFFEF4444);
  static const Color blue = Color(0xFF60A5FA);
}

class AppSpacing {
  AppSpacing._();

  static const double page = 20;
  static const double cardPadding = 18;
  static const double gap = 12;
  static const double radiusLg = 22;
  static const double radiusMd = 16;
  static const double radiusSm = 12;
  static const double radius = radiusMd;
}

/// Breakpoint helper reused across screens to switch between phone and
/// tablet/desktop layouts (wider stat strips, side-by-side panels, etc).
bool isDesktop(BuildContext context) => MediaQuery.of(context).size.width >= 900;

class AppTheme {
  AppTheme._();

  static ThemeData dark() {
    final base = ThemeData.dark(useMaterial3: true);

    final textTheme = base.textTheme
        .copyWith(
          displaySmall: const TextStyle(
            fontFamily: 'Fraunces',
            fontWeight: FontWeight.w600,
            fontSize: 32,
            color: AppColors.text,
            height: 1.15,
          ),
          titleLarge: const TextStyle(
            fontFamily: 'Fraunces',
            fontWeight: FontWeight.w600,
            fontSize: 22,
            color: AppColors.text,
          ),
          titleMedium: const TextStyle(
            fontFamily: 'PlusJakartaSans',
            fontWeight: FontWeight.w800,
            fontSize: 16,
            color: AppColors.text,
          ),
          bodyLarge: const TextStyle(
            fontFamily: 'PlusJakartaSans',
            fontSize: 15,
            color: AppColors.text,
          ),
          bodyMedium: const TextStyle(
            fontFamily: 'PlusJakartaSans',
            fontSize: 13.5,
            color: AppColors.muted,
          ),
          labelLarge: const TextStyle(
            fontFamily: 'PlusJakartaSans',
            fontWeight: FontWeight.w800,
            fontSize: 14,
            color: AppColors.text,
          ),
        )
        .apply(fontFamily: 'PlusJakartaSans');

    return base.copyWith(
      scaffoldBackgroundColor: AppColors.background,
      primaryColor: AppColors.primaryGold,
      colorScheme: base.colorScheme.copyWith(
        primary: AppColors.primaryGold,
        secondary: AppColors.primaryGold,
        surface: AppColors.surface,
        error: AppColors.red,
        onPrimary: AppColors.background,
        onSurface: AppColors.text,
      ),
      textTheme: textTheme,
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          fontFamily: 'PlusJakartaSans',
          fontWeight: FontWeight.w800,
          fontSize: 17,
          color: AppColors.text,
        ),
        iconTheme: IconThemeData(color: AppColors.text),
      ),
      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
          side: const BorderSide(color: AppColors.border),
        ),
      ),
      dividerTheme: const DividerThemeData(color: AppColors.border, thickness: 1),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceRaised,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        labelStyle: const TextStyle(color: AppColors.muted),
        hintStyle: const TextStyle(color: AppColors.faint),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.primaryGold, width: 1.4),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.red),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primaryGold,
          foregroundColor: AppColors.background,
          textStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.text,
          side: const BorderSide(color: AppColors.borderStrong),
          textStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primaryGold,
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      chipTheme: base.chipTheme.copyWith(
        backgroundColor: AppColors.surfaceRaised,
        selectedColor: AppColors.primaryGold,
        side: const BorderSide(color: AppColors.border),
        labelStyle: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w700),
        secondaryLabelStyle: const TextStyle(color: AppColors.background, fontWeight: FontWeight.w800),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.background,
        selectedItemColor: AppColors.primaryGold,
        unselectedItemColor: AppColors.faint,
        type: BottomNavigationBarType.fixed,
        showUnselectedLabels: true,
        elevation: 0,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primaryGold,
        linearTrackColor: AppColors.surfaceRaised,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.surfaceRaised,
        contentTextStyle: const TextStyle(color: AppColors.text),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
      ),
    );
  }
}
