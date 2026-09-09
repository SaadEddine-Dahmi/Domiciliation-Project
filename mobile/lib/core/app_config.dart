// lib/core/app_config.dart
//
// Single source of truth for the app's display name and brand-level
// constants. Change ONLY this file when the final product name is chosen —
// nothing else in the codebase should hardcode a brand string.

class AppConfig {
  AppConfig._();

  /// Product display name.
  static const String appName = 'DomPro';

  /// Short tagline shown under the wordmark on the login screen.
  static const String tagline = 'Plateforme de gestion de domiciliation';
}
