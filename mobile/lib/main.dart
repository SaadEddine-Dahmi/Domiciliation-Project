import 'package:flutter/material.dart';

import 'core/api_client.dart';
import 'core/auth_store.dart';
import 'core/theme_controller.dart';
import 'models/app_user.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'theme/app_design.dart';

const defaultApiBase = String.fromEnvironment(
  'API_BASE',
  defaultValue: 'http://localhost:8000',
);

void main() {
  runApp(const DomiciliationApp());
}

class DomiciliationApp extends StatelessWidget {
  const DomiciliationApp({super.key});

  @override
  Widget build(BuildContext context) {
    return const AuthGate();
  }
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late final ApiClient api;
  late final ThemeController themeController;
  AppUser? user;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    api = ApiClient(baseUrl: defaultApiBase);
    themeController = ThemeController();
    restore();
  }

  Future<void> restore() async {
    await themeController.load();
    final saved = await AuthStore.load();
    api.token = saved.token;

    if (saved.user == null || saved.token == null) {
      setState(() => loading = false);
      return;
    }

    try {
      final fresh = await api.me();
      await AuthStore.save(fresh, saved.token!);
      setState(() {
        user = fresh;
        loading = false;
      });
    } catch (_) {
      await AuthStore.clear();
      setState(() => loading = false);
    }
  }

  Future<void> handleLogin(AppUser nextUser, String token) async {
    api.token = token;
    await AuthStore.save(nextUser, token);
    setState(() => user = nextUser);
  }

  Future<void> handleLogout() async {
    try {
      await api.logout();
    } catch (_) {
      // A stale token should not block local sign-out.
    }

    api.token = null;
    await AuthStore.clear();
    setState(() => user = null);
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: themeController,
      builder: (context, _) {
        return MaterialApp(
          debugShowCheckedModeBanner: false,
          title: 'Domiciliation Mobile',
          themeMode: themeController.mode,
          theme: buildAppTheme(Brightness.light),
          darkTheme: buildAppTheme(Brightness.dark),
          home: buildHome(),
        );
      },
    );
  }

  Widget buildHome() {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (user == null) {
      return LoginScreen(api: api, onLoggedIn: handleLogin);
    }

    return HomeShell(
      api: api,
      user: user!,
      themeController: themeController,
      onLogout: handleLogout,
    );
  }
}

ThemeData buildAppTheme(Brightness brightness) {
  final isDark = brightness == Brightness.dark;
  final colorScheme = ColorScheme.fromSeed(
    seedColor: const Color(0xffc8a96e),
    brightness: brightness,
  );

  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: colorScheme.copyWith(
      primary: const Color(0xffc8a96e),
      secondary: const Color(0xffc8a96e),
      surface: const Color(0xff13161f),
      error: const Color(0xffef4444),
    ),
    fontFamily: 'Plus Jakarta Sans',
    scaffoldBackgroundColor: AppColors.background,
    appBarTheme: const AppBarTheme(
      centerTitle: false,
      elevation: 0,
      backgroundColor: AppColors.background,
      foregroundColor: AppColors.text,
    ),
    cardTheme: CardThemeData(
      color: AppColors.surface,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppSpacing.radius),
        side: const BorderSide(color: AppColors.border),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      height: 76,
      backgroundColor: AppColors.surface.withOpacity(0.96),
      indicatorColor: AppColors.primaryGold.withOpacity(0.16),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontSize: 11,
          color: states.contains(WidgetState.selected) ? AppColors.primaryGold : AppColors.muted,
          fontWeight: states.contains(WidgetState.selected) ? FontWeight.w800 : FontWeight.w600,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          color: states.contains(WidgetState.selected) ? AppColors.primaryGold : AppColors.muted,
        ),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 52),
        backgroundColor: AppColors.primaryGold,
        foregroundColor: AppColors.background,
        textStyle: const TextStyle(fontWeight: FontWeight.w900),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 52),
        foregroundColor: AppColors.text,
        side: const BorderSide(color: AppColors.border),
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.surfaceRaised,
      prefixIconColor: AppColors.primaryGold,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.primaryGold, width: 1.2),
      ),
      labelStyle: const TextStyle(color: AppColors.muted),
      hintStyle: const TextStyle(color: AppColors.soft),
    ),
    dividerTheme: const DividerThemeData(color: AppColors.border),
    drawerTheme: const DrawerThemeData(backgroundColor: AppColors.surface),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: isDark ? AppColors.surfaceRaised : AppColors.surface,
      contentTextStyle: const TextStyle(color: AppColors.text),
    ),
    textTheme: ThemeData.dark().textTheme.apply(bodyColor: AppColors.text, displayColor: AppColors.text).copyWith(
          displaySmall: const TextStyle(fontFamily: 'Fraunces', fontWeight: FontWeight.w700, color: AppColors.text),
          headlineMedium: const TextStyle(fontFamily: 'Fraunces', fontWeight: FontWeight.w800, color: AppColors.text),
          headlineSmall: const TextStyle(fontFamily: 'Fraunces', fontWeight: FontWeight.w800, color: AppColors.text),
          titleLarge: const TextStyle(fontWeight: FontWeight.w900, color: AppColors.text),
          bodyMedium: const TextStyle(color: AppColors.muted),
        ),
  );
}
