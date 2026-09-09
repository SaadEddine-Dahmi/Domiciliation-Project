import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

import '../models/app_user.dart';
import '../models/login_result.dart';
import '../models/register_result.dart';
import 'api_exception.dart';

class ApiClient {
  ApiClient({required this.baseUrl});

  final String baseUrl;
  String? token;

  Uri uri(String path, [Map<String, String>? query]) {
    final cleanBase = baseUrl.replaceFirst(RegExp(r'/$'), '');
    final cleanPath = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$cleanBase$cleanPath').replace(queryParameters: query);
  }

  Map<String, String> get headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> getJson(String path) async {
    try {
      final response = await http.get(uri(path), headers: headers);
      return decode(response);
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<Map<String, dynamic>> getJsonQuery(String path, Map<String, String> query) async {
    try {
      final response = await http.get(uri(path, query), headers: headers);
      return decode(response);
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<Map<String, dynamic>> postJson(String path, [Map<String, dynamic>? body]) async {
    try {
      final response = await http.post(uri(path), headers: headers, body: jsonEncode(body ?? {}));
      return decode(response);
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<Map<String, dynamic>> putJson(String path, [Map<String, dynamic>? body]) async {
    try {
      final response = await http.put(uri(path), headers: headers, body: jsonEncode(body ?? {}));
      return decode(response);
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<Map<String, dynamic>> patchJson(String path, [Map<String, dynamic>? body]) async {
    try {
      final response = await http.patch(uri(path), headers: headers, body: jsonEncode(body ?? {}));
      return decode(response);
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<void> deleteJson(String path) async {
    try {
      final response = await http.delete(uri(path), headers: headers);
      decode(response);
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<String> getTextQuery(String path, Map<String, String> query) async {
    try {
      final response = await http.get(uri(path, query), headers: {
        'Accept': query['format'] == 'html' ? 'text/html' : 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      });
      if (response.statusCode >= 200 && response.statusCode < 300) return response.body;
      decode(response);
      return response.body;
    } on http.ClientException catch (error) {
      throw ApiException(networkMessage(error));
    }
  }

  Future<LoginResult> login(String email, String password) async {
    final data = await postJson('/api/auth/login', {'email': email, 'password': password});
    final payload = data['data'] as Map<String, dynamic>;
    return LoginResult(
      user: AppUser.fromJson(payload['user'] as Map<String, dynamic>),
      token: payload['token'] as String,
    );
  }

  Future<RegisterResult> register({
    required String nom,
    String? prenom,
    required String email,
    required String password,
    String? telephone,
  }) async {
    final data = await postJson('/api/auth/register', {
      'nom': nom,
      if (prenom != null) 'prenom': prenom,
      'email': email,
      'password': password,
      if (telephone != null) 'telephone': telephone,
      'role': 'domiciliataire',
    });
    final payload = Map<String, dynamic>.from(data['data'] as Map? ?? const {});
    final token = payload['token']?.toString();
    final userJson = payload['user'];

    return RegisterResult(
      pending: token == null || token.isEmpty,
      user: userJson is Map ? AppUser.fromJson(Map<String, dynamic>.from(userJson)) : null,
      token: token,
    );
  }

  Future<AppUser> me() async {
    final data = await getJson('/api/auth/me');
    return AppUser.fromJson(data['data'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    await postJson('/api/auth/logout');
  }

  Future<Map<String, dynamic>> dashboardStats() async {
    final data = await getJson('/api/dashboard/stats');
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<List<dynamic>> list(String path) async {
    final data = await getJson(path);
    return List<dynamic>.from(data['data'] as List? ?? const []);
  }

  Future<List<dynamic>> listQuery(String path, Map<String, String> query) async {
    final data = await getJsonQuery(path, query);
    return List<dynamic>.from(data['data'] as List? ?? const []);
  }

  Future<List<dynamic>> pendingDomiciliataires() => list('/api/admin/users/pending');

  Future<void> approveDomiciliataire(Object id, String activationDate) async {
    await postJson('/api/admin/users/$id/approve', {'activation_date': activationDate});
  }

  Future<void> rejectDomiciliataire(Object id, String reason) async {
    await postJson('/api/admin/users/$id/reject', {'reason': reason});
  }

  Future<Map<String, dynamic>> createClient(Map<String, dynamic> payload) async {
    final data = await postJson('/api/clients', payload);
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> getClient(Object id) async {
    final data = await getJson('/api/clients/$id');
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> updateClient(Object id, Map<String, dynamic> payload) async {
    final data = await putJson('/api/clients/$id', payload);
    return Map<String, dynamic>.from(data);
  }

  Future<void> setClientStatus(Object id, String statut) async {
    await patchJson('/api/clients/$id/status', {'statut': statut});
  }

  Future<Map<String, dynamic>> upsertRepresentant(Object entrepriseId, Map<String, dynamic> payload) async {
    final data = await putJson('/api/entreprises/$entrepriseId/representant', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<String?> resetClientPassword(Object id) async {
    final data = await postJson('/api/clients/$id/regenerate-password');
    return data['generated_password']?.toString();
  }

  Future<void> sendMessage({required Object clientUserId, required String message, String? subject}) async {
    await postJson('/api/messages', {
      'client_user_id': clientUserId,
      'message': message,
      if (subject != null && subject.trim().isNotEmpty) 'subject': subject.trim(),
    });
  }

  Future<void> markMessageRead(Object id) async {
    await postJson('/api/messages/$id/read');
  }

  Future<Map<String, dynamic>> messageReceipt(Object id) async {
    final data = await getJson('/api/messages/$id/receipt');
    return Map<String, dynamic>.from(data['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> createArticle(Map<String, dynamic> payload) async {
    final data = await postJson('/api/articles', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> updateArticle(Object id, Map<String, dynamic> payload) async {
    final data = await putJson('/api/articles/$id', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<void> deleteArticle(Object id) async {
    await deleteJson('/api/articles/$id');
  }

  Future<Map<String, dynamic>> createTemplate(Map<String, dynamic> payload) async {
    final data = await postJson('/api/templates', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> updateTemplate(Object id, Map<String, dynamic> payload) async {
    final data = await putJson('/api/templates/$id', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<void> deleteTemplate(Object id) async {
    await deleteJson('/api/templates/$id');
  }

  Future<List<dynamic>> documentTypes() => list('/api/document-types');

  Future<Map<String, dynamic>> createDocumentType(Map<String, dynamic> payload) async {
    final data = await postJson('/api/document-types', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> createContract(Map<String, dynamic> payload) async {
    final data = await postJson('/api/contrats', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> updateContract(Object id, Map<String, dynamic> payload) async {
    final data = await putJson('/api/contrats/$id', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<void> terminateContract(Object id) async {
    await postJson('/api/contrats/$id/terminate');
  }

  Future<void> archiveContract(Object id) async {
    await postJson('/api/contrats/$id/archive');
  }

  Future<void> deleteContract(Object id) async {
    await deleteJson('/api/contrats/$id');
  }

  Future<void> renewContract(Object id) async {
    await postJson('/api/contrats/$id/renew');
  }

  Future<void> createPayment(Object contractId, Map<String, dynamic> payload) async {
    await postJson('/api/contrats/$contractId/paiements', payload);
  }

  Future<List<dynamic>> contractPayments(Object contractId) => list('/api/contrats/$contractId/paiements');

  Future<Map<String, dynamic>> contractPaymentSummary(Object contractId) async {
    final data = await getJson('/api/contrats/$contractId/paiements/summary');
    return Map<String, dynamic>.from(data['data'] as Map? ?? const {});
  }

  Future<void> archiveFacture(Object id) async {
    await postJson('/api/factures/$id/archive');
  }

  Future<void> restoreFacture(Object id) async {
    await postJson('/api/factures/$id/restore');
  }

  Future<void> deleteFacture(Object id) async {
    await deleteJson('/api/factures/$id');
  }

  String facturePdfUrl(Object id, {String mode = 'preview'}) {
    return uri('/api/factures/$id/pdf', {'token': token ?? '', 'mode': mode}).toString();
  }

  Future<void> activateContract(Object id, PlatformFile file) async {
    final request = http.MultipartRequest('POST', uri('/api/contrats/$id/activate'));
    request.headers.addAll({'Accept': 'application/json', if (token != null) 'Authorization': 'Bearer $token'});
    if (file.bytes != null) {
      request.files.add(http.MultipartFile.fromBytes('signed_pdf', file.bytes!, filename: file.name));
    } else if (file.path != null) {
      request.files.add(await http.MultipartFile.fromPath('signed_pdf', file.path!));
    } else {
      throw ApiException('Fichier invalide.');
    }
    final streamed = await request.send();
    decode(await http.Response.fromStream(streamed));
  }

  Future<Map<String, dynamic>> uploadDocument({
    required Object entrepriseId,
    required Object documentTypeId,
    required PlatformFile file,
    String? dateExpiration,
  }) async {
    final request = http.MultipartRequest('POST', uri('/api/documents'));
    request.headers.addAll({'Accept': 'application/json', if (token != null) 'Authorization': 'Bearer $token'});
    request.fields['entreprise_id'] = entrepriseId.toString();
    request.fields['document_type_id'] = documentTypeId.toString();
    if (dateExpiration != null && dateExpiration.trim().isNotEmpty) request.fields['date_expiration'] = dateExpiration.trim();
    if (file.bytes != null) {
      request.files.add(http.MultipartFile.fromBytes('file', file.bytes!, filename: file.name));
    } else if (file.path != null) {
      request.files.add(await http.MultipartFile.fromPath('file', file.path!));
    } else {
      throw ApiException('Fichier invalide.');
    }
    final streamed = await request.send();
    final data = decode(await http.Response.fromStream(streamed));
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<void> deleteDocument(Object id) async {
    await deleteJson('/api/documents/$id');
  }

  Future<Map<String, dynamic>> updateDocument(Object id, Map<String, dynamic> payload) async {
    final data = await putJson('/api/documents/$id', payload);
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> profile() async {
    final data = await getJson('/api/profile');
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<void> updateProfile(Map<String, dynamic> payload) async {
    await putJson('/api/profile', payload);
  }

  Future<Map<String, dynamic>> uploadProfilePhoto(PlatformFile file) async {
    final request = http.MultipartRequest('POST', uri('/api/profile/photo'));
    request.headers.addAll({'Accept': 'application/json', if (token != null) 'Authorization': 'Bearer $token'});
    if (file.bytes != null) {
      request.files.add(http.MultipartFile.fromBytes('photo', file.bytes!, filename: file.name));
    } else if (file.path != null) {
      request.files.add(await http.MultipartFile.fromPath('photo', file.path!));
    } else {
      throw ApiException('Fichier invalide.');
    }
    final data = decode(await http.Response.fromStream(await request.send()));
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<void> deleteProfilePhoto() async {
    await deleteJson('/api/profile/photo');
  }

  Future<void> changePassword({required String currentPassword, required String password, required String confirmation}) async {
    await putJson('/api/account/password', {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': confirmation,
    });
  }

  Future<List<dynamic>> accountHistory({int limit = 8}) => listQuery('/api/account/history', {'limit': '$limit'});

  String accountHistoryExportUrl(String format) {
    return uri('/api/account/history/export', {'token': token ?? '', 'format': format}).toString();
  }

  Future<String> accountHistoryExport(String format) => getTextQuery('/api/account/history/export', {'format': format});

  Future<int> unreadNotificationsCount() async {
    final data = await getJson('/api/notifications/unread-count');
    final payload = data['data'];
    if (payload is Map) return int.tryParse('${payload['unread_notifications_count'] ?? 0}') ?? 0;
    return int.tryParse('${data['count'] ?? payload ?? 0}') ?? 0;
  }

  Future<void> markNotificationRead(Object id) async {
    await postJson('/api/notifications/$id/read');
  }

  Future<void> markAllNotificationsRead() async {
    await postJson('/api/notifications/read-all');
  }

  String contractPdfUrl(Object id) {
    return uri('/api/contrats/$id/pdf/stream', {'token': token ?? '', 'mode': 'preview'}).toString();
  }

  String contractPdfStreamUrl(Object contractId) => contractPdfUrl(contractId);

  String documentPreviewUrl(Object id) => uri('/api/documents/$id/preview', {'token': token ?? ''}).toString();
  String documentDownloadUrl(Object id) => uri('/api/documents/$id/download', {'token': token ?? ''}).toString();

  Map<String, dynamic> decode(http.Response response) {
    final body = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 200 && response.statusCode < 300) return body;
    final message = body['message'] ?? (body['errors'] is Map ? (body['errors'] as Map).values.first.first : 'Erreur API (${response.statusCode})');
    throw ApiException(message.toString());
  }

  String networkMessage(http.ClientException error) {
    return 'Impossible de joindre le serveur API. Verifiez que Laravel tourne sur l URL API_BASE et que CORS autorise le port Flutter Web local. Detail: ${error.message}';
  }
}
