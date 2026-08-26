import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../models/app_user.dart';
import '../theme/app_design.dart';
import '../widgets/error_banner.dart';
import '../widgets/premium_card.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.api, required this.user});

  final ApiClient api;
  final AppUser user;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? profile;
  String? error;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final data = await widget.api.profile();
      if (!mounted) return;
      setState(() {
        profile = data;
        loading = false;
        error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e is ApiException ? e.message : e.toString();
        loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());

    final data = profile ?? {};
    final company = textValue(data['nom_societe'], fallback: widget.user.name);
    final percent = profileCompletion(data);
    final addresses = normaliseAddresses(data['adresses'] ?? data['addresses']);
    final representant = data['representant'] is Map ? data['representant'] as Map : const {};

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: [
            if (error != null) ...[
              ErrorBanner(message: error!),
              const SizedBox(height: 14),
            ],
            PremiumCard(
              child: Row(
                children: [
                  SizedBox(
                    width: 92,
                    height: 92,
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        CircularProgressIndicator(
                          value: percent / 100,
                          strokeWidth: 8,
                          color: AppColors.primaryGold,
                          backgroundColor: AppColors.surfaceRaised,
                        ),
                        Text('$percent%', style: const TextStyle(fontFamily: 'Fraunces', fontSize: 24, fontWeight: FontWeight.w900, color: AppColors.primaryGold)),
                      ],
                    ),
                  ),
                  const SizedBox(width: 18),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Profil complete a $percent%', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
                        const SizedBox(height: 6),
                        const Text('Ces informations alimentent vos contrats et documents.', style: TextStyle(color: AppColors.muted)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _SectionCard(
              title: 'Identite entreprise',
              rows: [
                _InfoRow('Raison sociale', company),
                _InfoRow('Forme juridique', textValue(data['forme_juridique'], fallback: 'SARL')),
                _InfoRow('Capital social', moneyValue(data['capital_social'] ?? data['capital'])),
                _InfoRow('ICE', textValue(data['ice'], fallback: textValue(data['if_fiscal']))),
                _InfoRow('RC', textValue(data['rc'])),
                _InfoRow('TP', textValue(data['tp'])),
              ],
            ),
            const SizedBox(height: 14),
            _SectionCard(
              title: 'Contact',
              rows: [
                _InfoRow('Nom complet', displayName(data)),
                _InfoRow('Email', widget.user.email),
                _InfoRow('Telephone', textValue(data['telephone'])),
              ],
            ),
            const SizedBox(height: 14),
            _SectionCard(
              title: 'Representant legal',
              rows: [
                _InfoRow('Nom', '${textValue(representant['prenom'])} ${textValue(representant['nom'])}'.trim()),
                _InfoRow('CIN / Passeport', textValue(representant['cin'] ?? data['identite_representant'])),
                _InfoRow('Nationalite', textValue(representant['nationalite'])),
                _InfoRow('Email', textValue(representant['email'] ?? data['representant_email'])),
                _InfoRow('Telephone', textValue(representant['telephone'] ?? data['representant_telephone'])),
              ],
            ),
            const SizedBox(height: 14),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const _SectionTitle('Adresses'),
                  const SizedBox(height: 10),
                  if (addresses.isEmpty)
                    const Text('Aucune adresse renseignee.', style: TextStyle(color: AppColors.muted))
                  else
                    for (final address in addresses)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.location_on_outlined, color: AppColors.primaryGold),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(address.label, style: const TextStyle(fontWeight: FontWeight.w900)),
                                  Text(address.value, style: const TextStyle(color: AppColors.muted)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  int profileCompletion(Map<String, dynamic> data) {
    final rep = data['representant'] is Map ? data['representant'] as Map : const {};
    final checks = [
      plainValue(data['nom_societe']).isNotEmpty,
      plainValue(rep['nom'] ?? data['representant_legal']).isNotEmpty,
      plainValue(rep['cin'] ?? data['identite_representant']).isNotEmpty,
      plainValue(data['rc']).isNotEmpty,
      plainValue(data['if_fiscal']).isNotEmpty,
      normaliseAddresses(data['adresses'] ?? data['addresses']).isNotEmpty,
    ];
    return ((checks.where((item) => item).length / checks.length) * 100).round();
  }

  List<_ProfileAddress> normaliseAddresses(dynamic raw) {
    if (raw is! List) return const [];
    return raw.asMap().entries.map((entry) {
      final item = entry.value;
      if (item is Map) {
        return _ProfileAddress(textValue(item['label'], fallback: 'Adresse ${entry.key + 1}'), textValue(item['value'] ?? item['adresse'] ?? item['address']));
      }
      return _ProfileAddress('Adresse ${entry.key + 1}', textValue(item));
    }).where((item) => item.value.isNotEmpty).toList();
  }

  String textValue(Object? value, {String fallback = '-'}) {
    final text = '${value ?? ''}'.trim();
    return text.isEmpty ? fallback : text;
  }

  String plainValue(Object? value) => '${value ?? ''}'.trim();

  String displayName(Map<String, dynamic> data) {
    final fullName = '${plainValue(data['prenom'])} ${plainValue(data['nom'])}'.trim();
    return fullName.isEmpty ? widget.user.name : fullName;
  }

  String moneyValue(Object? value) {
    final text = textValue(value, fallback: '');
    return text.isEmpty ? '-' : '$text DH';
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.rows});

  final String title;
  final List<_InfoRow> rows;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(title),
          const SizedBox(height: 12),
          for (final row in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: Text(row.label, style: const TextStyle(color: AppColors.muted))),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      row.value.isEmpty ? '-' : row.value,
                      textAlign: TextAlign.right,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(color: AppColors.primaryGold, fontWeight: FontWeight.w900, fontSize: 12, letterSpacing: 1),
    );
  }
}

class _InfoRow {
  const _InfoRow(this.label, this.value);

  final String label;
  final String value;
}

class _ProfileAddress {
  const _ProfileAddress(this.label, this.value);

  final String label;
  final String value;
}
