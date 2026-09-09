import 'package:dompro/core/api_client.dart';
import 'package:dompro/core/api_exception.dart';
import 'package:dompro/core/auth_store.dart';
import 'package:dompro/core/theme_controller.dart';
import 'package:dompro/models/app_user.dart';
import 'package:dompro/widgets/api_future.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('AppUser', () {
    test('parses complete API payload and role helpers', () {
      final user = AppUser.fromJson({
        'id': '42',
        'email': 'admin@example.com',
        'role': 'admin',
        'status': 'active',
        'nom': 'Admin',
        'prenom': 'User',
        'must_change_password': true,
      });

      expect(user.id, 42);
      expect(user.name, 'Admin User');
      expect(user.mustChangePassword, isTrue);
      expect(user.isAdmin, isTrue);
      expect(user.isDomiciliataire, isFalse);
      expect(user.isClient, isFalse);
      expect(user.roleLabel, 'Super Admin');
    });

    test('uses safe defaults for sparse payloads', () {
      final user = AppUser.fromJson({'email': 'client@example.com'});

      expect(user.id, 0);
      expect(user.name, 'client@example.com');
      expect(user.role, 'client');
      expect(user.status, 'active');
      expect(user.isClient, isTrue);
      expect(user.roleLabel, 'Client');
    });
  });

  group('ApiClient helpers', () {
    test('normalizes base URL, path and query parameters', () {
      final client = ApiClient(baseUrl: 'http://localhost:8000/');

      expect(
        client.uri('api/contrats', {'include_archived': '1'}).toString(),
        'http://localhost:8000/api/contrats?include_archived=1',
      );
    });

    test('adds bearer token only when present', () {
      final client = ApiClient(baseUrl: 'http://localhost:8000');

      expect(client.headers, {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      });

      client.token = 'abc123';

      expect(client.headers['Authorization'], 'Bearer abc123');
    });

    test('decodes success responses and throws API messages for failures', () {
      final client = ApiClient(baseUrl: 'http://localhost:8000');

      expect(
        client.decode(http.Response('{"success":true,"data":{"id":1}}', 200)),
        {'success': true, 'data': {'id': 1}},
      );

      expect(
        () => client.decode(http.Response('{"message":"Unauthorized"}', 401)),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Unauthorized')),
      );
    });

    test('decodes validation errors when no top-level message is present', () {
      final client = ApiClient(baseUrl: 'http://localhost:8000');

      expect(
        () => client.decode(http.Response('{"errors":{"email":["Email requis"]}}', 422)),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Email requis')),
      );
    });

    test('builds authenticated preview/download URLs', () {
      final client = ApiClient(baseUrl: 'http://localhost:8000');
      client.token = 'token value';

      expect(
        client.contractPdfUrl(9),
        'http://localhost:8000/api/contrats/9/pdf/stream?token=token+value&mode=preview',
      );
      expect(
        client.facturePdfUrl(3, mode: 'download'),
        'http://localhost:8000/api/factures/3/pdf?token=token+value&mode=download',
      );
    });
  });

  group('AuthStore', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('returns empty state when no session is saved', () async {
      final loaded = await AuthStore.load();

      expect(loaded.user, isNull);
      expect(loaded.token, isNull);
    });

    test('saves, loads and clears session state', () async {
      final user = AppUser(
        id: 5,
        email: 'dom@example.com',
        role: 'domiciliataire',
        status: 'active',
        name: 'Tenant User',
        mustChangePassword: true,
      );

      await AuthStore.save(user, 'secret-token');
      final loaded = await AuthStore.load();

      expect(loaded.token, 'secret-token');
      expect(loaded.user?.id, 5);
      expect(loaded.user?.email, 'dom@example.com');
      expect(loaded.user?.roleLabel, 'Domiciliataire');
      expect(loaded.user?.mustChangePassword, isTrue);

      await AuthStore.clear();
      final cleared = await AuthStore.load();

      expect(cleared.user, isNull);
      expect(cleared.token, isNull);
    });
  });

  group('ThemeController', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('loads system by default and persists selected modes', () async {
      final controller = ThemeController();

      await controller.load();
      expect(controller.mode, ThemeMode.system);

      await controller.setMode(ThemeMode.dark);
      expect(controller.mode, ThemeMode.dark);
      expect(controller.isDark, isTrue);

      final reloaded = ThemeController();
      await reloaded.load();
      expect(reloaded.mode, ThemeMode.dark);

      await reloaded.toggleDarkLight();
      expect(reloaded.mode, ThemeMode.light);
    });
  });

  group('ApiFuture', () {
    testWidgets('renders loading then success and refreshes data', (tester) async {
      var calls = 0;

      await tester.pumpWidget(MaterialApp(
        home: ApiFuture<int>(
          load: () async => ++calls,
          builder: (context, data, refresh) => Column(
            children: [
              Text('value $data'),
              TextButton(onPressed: refresh, child: const Text('refresh')),
            ],
          ),
        ),
      ));

      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      await tester.pumpAndSettle();
      expect(find.text('value 1'), findsOneWidget);

      await tester.tap(find.text('refresh'));
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      await tester.pumpAndSettle();
      expect(find.text('value 2'), findsOneWidget);
    });

    testWidgets('renders ApiException messages', (tester) async {
      await tester.pumpWidget(MaterialApp(
        home: ApiFuture<int>(
          load: () async => throw ApiException('Boom'),
          builder: (context, data, refresh) => Text('$data'),
        ),
      ));

      await tester.pumpAndSettle();

      expect(find.text('Boom'), findsOneWidget);
    });
  });
}
