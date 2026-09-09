// lib/screens/contract_pdf_preview_screen.dart
//
// "Aperçu du contrat" — new screen matching image 6 (left panel): a
// white "paper" card floating on the dark background showing the
// contract's textual preview, with a gold "Télécharger PDF" button
// pinned at the bottom.
//
// This renders a lightweight in-app text preview (title, parties,
// articles) rather than an embedded PDF viewer, since no PDF-rendering
// package is confirmed in your pubspec. The real PDF is one tap away via
// contractPdfUrl(). If you'd like a true embedded PDF (via a package like
// `printing` or `flutter_pdfview`), tell me and I'll swap this preview
// for an actual PDF render.

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/link_launcher.dart';
import '../theme/app_design.dart';
import '../widgets/premium_button.dart';

class ContractPdfPreviewScreen extends StatelessWidget {
  const ContractPdfPreviewScreen(
      {super.key, required this.api, required this.contract});

  final ApiClient api;
  final dynamic contract;

  @override
  Widget build(BuildContext context) {
    final articles =
        List<dynamic>.from(contract['articles'] as List? ?? const []);
    final entreprise = contract['entreprise'] as Map?;
    final title = '${contract['titre_contrat'] ?? 'Contrat de domiciliation'}';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Aperçu du contrat'),
        actions: [
          IconButton(
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.close))
        ],
      ),
      backgroundColor: AppColors.background,
      body: Column(
        children: [
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(26),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(6),
                  boxShadow: [
                    BoxShadow(
                        color: Colors.black.withOpacity(0.4),
                        blurRadius: 24,
                        offset: const Offset(0, 12))
                  ],
                ),
                child: DefaultTextStyle(
                  style: const TextStyle(
                      color: Color(0xFF1A1A1A), fontSize: 12.5, height: 1.5),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Center(
                        child: Text(
                          title.toUpperCase(),
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                              letterSpacing: 0.4),
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text('Entre les soussignés :',
                          style: TextStyle(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 10),
                      const Text('1. Le domiciliataire',
                          style: TextStyle(
                              fontWeight: FontWeight.w800,
                              decoration: TextDecoration.underline)),
                      const SizedBox(height: 4),
                      Text(
                          '${contract['domiciliataire']?['nom_societe'] ?? 'Le domiciliataire'}, ci-après dénommée "le Domiciliataire".'),
                      const SizedBox(height: 12),
                      const Text('2. Le domicilié',
                          style: TextStyle(
                              fontWeight: FontWeight.w800,
                              decoration: TextDecoration.underline)),
                      const SizedBox(height: 4),
                      Text(
                          '${entreprise?['raison_sociale'] ?? '—'}, ci-après dénommée "le Domicilié".'),
                      const SizedBox(height: 20),
                      const Text('Il a été convenu ce qui suit :',
                          style: TextStyle(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 14),
                      for (var i = 0; i < articles.length; i++) ...[
                        Text(
                            'ARTICLE ${i + 1} — ${(articles[i]['title'] ?? '').toString().toUpperCase()}',
                            style: const TextStyle(
                                fontWeight: FontWeight.w900, fontSize: 12.5)),
                        const SizedBox(height: 4),
                        Text('${articles[i]['body'] ?? ''}'),
                        const SizedBox(height: 14),
                      ],
                      if (articles.isEmpty)
                        const Text('Aucune clause associée à ce contrat.'),
                      const SizedBox(height: 10),
                      Align(
                        alignment: Alignment.centerRight,
                        child: Text('1 / 1',
                            style: TextStyle(
                                color: Colors.black.withOpacity(0.4),
                                fontSize: 11)),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
              child: PremiumButton(
                icon: Icons.download_outlined,
                label: 'Télécharger PDF',
                onPressed: () =>
                    openExternal(api.contractPdfUrl(contract['id'])),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
