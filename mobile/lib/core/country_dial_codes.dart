class CountryDialCode {
  const CountryDialCode(this.iso, this.country, this.dialCode);

  final String iso;
  final String country;
  final String dialCode;
}

const countryDialCodes = <CountryDialCode>[
  CountryDialCode('AF', 'Afghanistan', '+93'),
  CountryDialCode('AL', 'Albanie', '+355'),
  CountryDialCode('AM', 'Armenie', '+374'),
  CountryDialCode('AO', 'Angola', '+244'),
  CountryDialCode('AR', 'Argentine', '+54'),
  CountryDialCode('AT', 'Autriche', '+43'),
  CountryDialCode('AU', 'Australie', '+61'),
  CountryDialCode('AZ', 'Azerbaidjan', '+994'),
  CountryDialCode('BA', 'Bosnie-Herzegovine', '+387'),
  CountryDialCode('BD', 'Bangladesh', '+880'),
  CountryDialCode('BG', 'Bulgarie', '+359'),
  CountryDialCode('BH', 'Bahrein', '+973'),
  CountryDialCode('BJ', 'Benin', '+229'),
  CountryDialCode('BR', 'Bresil', '+55'),
  CountryDialCode('CA', 'Canada', '+1'),
  CountryDialCode('CH', 'Suisse', '+41'),
  CountryDialCode('CL', 'Chili', '+56'),
  CountryDialCode('CM', 'Cameroun', '+237'),
  CountryDialCode('CN', 'Chine', '+86'),
  CountryDialCode('CO', 'Colombie', '+57'),
  CountryDialCode('CZ', 'Tchequie', '+420'),
  CountryDialCode('DK', 'Danemark', '+45'),
  CountryDialCode('EG', 'Egypte', '+20'),
  CountryDialCode('FI', 'Finlande', '+358'),
  CountryDialCode('GA', 'Gabon', '+241'),
  CountryDialCode('GH', 'Ghana', '+233'),
  CountryDialCode('GR', 'Grece', '+30'),
  CountryDialCode('HK', 'Hong Kong', '+852'),
  CountryDialCode('HR', 'Croatie', '+385'),
  CountryDialCode('HU', 'Hongrie', '+36'),
  CountryDialCode('ID', 'Indonesie', '+62'),
  CountryDialCode('IE', 'Irlande', '+353'),
  CountryDialCode('IL', 'Israel', '+972'),
  CountryDialCode('IN', 'Inde', '+91'),
  CountryDialCode('IQ', 'Irak', '+964'),
  CountryDialCode('JO', 'Jordanie', '+962'),
  CountryDialCode('JP', 'Japon', '+81'),
  CountryDialCode('KE', 'Kenya', '+254'),
  CountryDialCode('KR', 'Coree du Sud', '+82'),
  CountryDialCode('KW', 'Koweit', '+965'),
  CountryDialCode('LB', 'Liban', '+961'),
  CountryDialCode('LU', 'Luxembourg', '+352'),
  CountryDialCode('MA', 'Maroc', '+212'),
  CountryDialCode('FR', 'France', '+33'),
  CountryDialCode('US', 'Etats-Unis', '+1'),
  CountryDialCode('GB', 'Royaume-Uni', '+44'),
  CountryDialCode('ES', 'Espagne', '+34'),
  CountryDialCode('DE', 'Allemagne', '+49'),
  CountryDialCode('IT', 'Italie', '+39'),
  CountryDialCode('BE', 'Belgique', '+32'),
  CountryDialCode('NL', 'Pays-Bas', '+31'),
  CountryDialCode('PT', 'Portugal', '+351'),
  CountryDialCode('DZ', 'Algerie', '+213'),
  CountryDialCode('TN', 'Tunisie', '+216'),
  CountryDialCode('SN', 'Senegal', '+221'),
  CountryDialCode('CI', "Cote d'Ivoire", '+225'),
  CountryDialCode('ML', 'Mali', '+223'),
  CountryDialCode('MR', 'Mauritanie', '+222'),
  CountryDialCode('MX', 'Mexique', '+52'),
  CountryDialCode('MY', 'Malaisie', '+60'),
  CountryDialCode('NE', 'Niger', '+227'),
  CountryDialCode('NG', 'Nigeria', '+234'),
  CountryDialCode('NO', 'Norvege', '+47'),
  CountryDialCode('NZ', 'Nouvelle-Zelande', '+64'),
  CountryDialCode('OM', 'Oman', '+968'),
  CountryDialCode('PK', 'Pakistan', '+92'),
  CountryDialCode('PL', 'Pologne', '+48'),
  CountryDialCode('QA', 'Qatar', '+974'),
  CountryDialCode('RO', 'Roumanie', '+40'),
  CountryDialCode('RS', 'Serbie', '+381'),
  CountryDialCode('RU', 'Russie', '+7'),
  CountryDialCode('SA', 'Arabie saoudite', '+966'),
  CountryDialCode('SE', 'Suede', '+46'),
  CountryDialCode('SG', 'Singapour', '+65'),
  CountryDialCode('TH', 'Thailande', '+66'),
  CountryDialCode('TR', 'Turquie', '+90'),
  CountryDialCode('TW', 'Taiwan', '+886'),
  CountryDialCode('UA', 'Ukraine', '+380'),
  CountryDialCode('AE', 'Emirats arabes unis', '+971'),
  CountryDialCode('ZA', 'Afrique du Sud', '+27'),
];

List<CountryDialCode> uniqueCountryDialCodes() {
  final seen = <String>{};
  final rows = <CountryDialCode>[];
  for (final item in countryDialCodes) {
    if (seen.add(item.dialCode)) rows.add(item);
  }
  return rows;
}

String joinPhone(String dialCode, String number) {
  final local = number.trim().replaceFirst(RegExp(r'^0+'), '');
  return local.isEmpty ? '' : '$dialCode $local';
}

({String dialCode, String number}) splitPhone(String? value) {
  final raw = (value ?? '').trim();
  final codes = countryDialCodes.map((item) => item.dialCode).toSet().toList()
    ..sort((a, b) => b.length.compareTo(a.length));

  for (final code in codes) {
    if (raw.startsWith(code)) {
      return (dialCode: code, number: raw.substring(code.length).trim());
    }
  }

  return (dialCode: '+212', number: raw.replaceFirst(RegExp(r'^\+'), ''));
}
