# Domiciliation Mobile

Flutter mobile client for the Laravel API in `../backend`.

## API base URL

The app reads the API URL from `--dart-define=API_BASE=...`.

Examples:

```bash
flutter run --dart-define=API_BASE=http://10.0.2.2:8000
flutter run --dart-define=API_BASE=http://192.168.1.15:8000
```

Use `10.0.2.2` for the Android emulator when Laravel runs on the host machine.
Use your LAN IP for a physical phone.

## Generate platform folders

This repository contains the Flutter package source. If `android/`, `ios/`,
`windows/`, or other platform folders are missing, generate them from inside
this directory:

```bash
flutter create .
flutter pub get
flutter run --dart-define=API_BASE=http://10.0.2.2:8000
```

## Implemented screens

- Login with `/api/auth/login`
- Session restore with `/api/auth/me`
- Role-aware dashboard with `/api/dashboard/stats`
- Theme toggle with persisted light/dark mode
- Super Admin pending domiciliataire approvals
- Super Admin domiciliataire list
- Contracts list with `/api/contrats`
- Contract draft creation, PDF preview, renewal, termination, signed PDF upload
- Draft contract deletion and contract archiving
- Factures list, PDF preview, archive/restore/delete
- Create facture/payment from a contract
- Clients list for domiciliataires with `/api/clients`
- Client create/edit/detail, status toggle, password reset
- Documents list and preview links with `/api/documents`
- Messages list and send-message workflow with `/api/messages`
- Notifications with `/api/notifications`

## Source layout

```text
lib/
  main.dart                 # App bootstrap, theme, auth gate
  core/
    api_client.dart         # Laravel HTTP client
    api_exception.dart      # API error type
    auth_store.dart         # Token/user persistence
    link_launcher.dart      # Opens PDFs/documents externally
    theme_controller.dart   # Persisted light/dark/system theme mode
  models/
    app_user.dart
    login_result.dart
    stat_item.dart
  screens/
    admin_approvals_screen.dart
    admin_domiciliataires_screen.dart
    client_form_screen.dart
    contract_form_screen.dart
    login_screen.dart
    home_shell.dart
    dashboard_screen.dart
    contracts_screen.dart
    clients_screen.dart
    documents_screen.dart
    messages_screen.dart
    notifications_screen.dart
  widgets/
    api_future.dart
    compact_tile.dart
    error_banner.dart
    header_card.dart
    resource_list.dart
    section_title.dart
    stat_card.dart
```
