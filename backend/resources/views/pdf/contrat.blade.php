{{--
    resources/views/pdf/contrat.blade.php

    Domiciliation contract PDF template — rendered by dompdf via
    ContratController::generatePdf() and ContratController::streamPdf().

    Design: formal French legal document style — Times New Roman, justified
    paragraphs, structured "D'une part / D'autre part" identification blocks,
    declaration clause, dynamic article list, two-column signature table.

    ── VARIABLE SOURCE ──────────────────────────────────────────────────────
    Every value in this file comes from the $tokens array, built by
    ContratController::buildTokenMap(). This file does not read
    $contrat->entreprise or $contrat->domiciliataire directly for display
    values — only $tokens — so there is a single source of truth for every
    printed field. $contrat is still passed in for titre_contrat and any
    metadata outside the token map.

    ── CONTRACT TITLE ───────────────────────────────────────────────────────
    $contrat->titre_contrat is typed freely by the domiciliataire in wizard
    step 1. Printed as-is. The fallback 'CONTRAT DE DOMICILIATION' applies
    ONLY when the value is null/empty — this is the single place that
    default is rendered (a matching default also exists in
    ContratController::store(), applied when saving to the database).

    ── SIÈGE / SUCCURSALES ──────────────────────────────────────────────────
    $tokens['domiciliataire_siege_succursales'] already contains the full
    inline block: "Siège N° ... – Succursale1 : ... ; Succursale 2 : ..."
    See ContratController::buildTokenMap() for how it's assembled from
    domiciliataire_profiles.adresses.

    ── ARTICLE BODIES ───────────────────────────────────────────────────────
    $articles bodies arrive PRE-RESOLVED and PRE-ESCAPED from the controller
    (resolveTokens() + e() already applied upstream). This template must NOT
    call e() again here — see ContratController::generatePdf()/streamPdf().
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $contrat->titre_contrat ?: 'Contrat de Domiciliation' }}</title>
    <style>
        @page {
            size: A4;
            margin: 18mm 16mm 22mm 16mm;
        }
        * { box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            color: #000;
            font-size: 11.5pt;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .contract-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 8px 0 4px;
            letter-spacing: 0.5px;
        }

        .contract-subtitle {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 4px 0 12px;
        }

        .ref-line {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .intro-line {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
            font-size: 11.5pt;
        }

        .party-block {
            margin-bottom: 14px;
            text-align: justify;
        }

        .party-title {
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 6px;
            font-size: 11.5pt;
        }

        .client-details-list {
            list-style: none;
            padding-left: 25px;
            margin: 6px 0 10px;
        }
        .client-details-list li { margin-bottom: 4px; }

        .section-heading {
            font-size: 13.5pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 24px 0 12px;
        }

        .article-block {
            margin-bottom: 12px;
            text-align: justify;
            page-break-inside: avoid;
        }

        .article-title {
            font-weight: bold;
            font-size: 11.5pt;
            text-transform: uppercase;
            margin-bottom: 4px;
            text-decoration: underline;
        }

        .article-body { text-align: justify; }
        .article-body p { margin: 0 0 6px 0; }

        .declaration-block {
            margin: 10px 0 14px;
            text-align: justify;
        }

        .signature-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .date-location {
            text-align: right;
            font-weight: bold;
            margin-bottom: 12px;
            font-size: 11.5pt;
        }

        .signature-mention {
            font-style: italic;
            text-align: center;
            margin-bottom: 12px;
            font-size: 11pt;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
            height: 100px;
        }

        .signature-label {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
            font-size: 11.5pt;
        }

        .signature-name {
            font-size: 11pt;
            margin-top: 6px;
            font-weight: normal;
            line-height: 1.3;
        }

        .footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            min-height: 35px;
            border-top: 1px solid #000;
            padding-top: 6px;
            font-size: 9pt;
            text-align: center;
            line-height: 1.4;
        }

        strong { font-weight: bold; }
    </style>
</head>
<body>

    {{-- ── HEADER ──────────────────────────────────────────────────────── --}}
    {{-- <div class="contract-title">{{ $tokens['domiciliataire_nom'] ?: '—' }}</div> --}}
    <div class="contract-subtitle">
        {{ $contrat->titre_contrat ?: 'CONTRAT DE DOMICILIATION' }}
    </div>
    {{-- @if ($tokens['instruction_no'])
        <div class="ref-line">Réf. N° {{ $tokens['instruction_no'] }}</div>
    @endif --}}

    <div class="intro-line">Entre les soussignés :</div>

    {{-- ── DOMICILIATAIRE (centre de domiciliation) ─────────────────────── --}}
    <div class="party-block">
        <div class="party-title">D'une part</div>
        <p>
            Le Centre De domiciliation <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong>,
            RC <strong>{{ $tokens['domiciliataire_rc'] ?: '—' }}</strong>,
            I.F : <strong>{{ $tokens['domiciliataire_if'] ?: '—' }}</strong>
            {{-- Full siège + succursales block, e.g.:
                 "sise à Siège N° 78 ... – Succursale1 : ... ; Succursale 2 : ..." --}}
            sise à <strong>{{ $tokens['domiciliataire_siege_succursales'] ?: '—' }}</strong>.<br>

            Représentée par <strong>{{ $tokens['domiciliataire_representant'] ?: '—' }}</strong>
            @if ($tokens['domiciliataire_identite_representant'])
                titulaire de la CIN N° <strong>{{ $tokens['domiciliataire_identite_representant'] }}</strong>
            @endif
            @if ($tokens['domiciliataire_representant_telephone'] || $tokens['domiciliataire_representant_email'])
                ,
                @if ($tokens['domiciliataire_representant_telephone'])
                    Tél : <strong>{{ $tokens['domiciliataire_representant_telephone'] }}</strong>
                @endif
                @if ($tokens['domiciliataire_representant_email'])
                    Email : <strong>{{ $tokens['domiciliataire_representant_email'] }}</strong>
                @endif
            @endif
            .<br>

            @if ($tokens['instruction_no'])
                Déclare par la présente suivant l'instruction No. : <strong>{{ $tokens['instruction_no'] }}</strong>,
            @else
                Déclare par la présente
            @endif
            Donner domiciliation à :
        </p>
    </div>

    {{-- ── CLIENT / DOMICILIÉ (entreprise wanting domiciliation) ──────────── --}}
    <div class="party-block">
        <div class="party-title">D'autre part</div>
        <p>
            <strong>{{ $tokens['raison_sociale'] ?: '—' }}</strong> à l'adresse suivante
            C/O <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong>
            <strong>{{ $tokens['ville_client'] ?: ($tokens['ville_signature'] ?: '—') }}</strong>.
        </p>

        <div class="declaration-block">
            Nous déclarons en outre avoir pris connaissance qu'en application des dispositions
            de l'article 93 du code de recouvrement des créances publiques, les rôles des
            impôts, états de produits et autres titres de perception régulièrement émis ont
            exécutions contre les redevables qui y sont inscrits, toutes autres personnes
            auprès desquelles les redevables ont élu domicile fiscal, avec leur accord.
        </div>

        <p style="margin-bottom:5px">
            LA SOCIETE « <strong>{{ $tokens['raison_sociale'] ?: '—' }}</strong> » — Représentée par :
        </p>
        <ul class="client-details-list">
            <li>
                ➤ <strong>{{ $tokens['gerant_nom'] ?: '—' }}</strong>,
                @if ($tokens['date_naissance'])
                    né(e) le <strong>{{ $tokens['date_naissance'] }}</strong>,
                @endif
                porteur de CIN/Passeport : <strong>{{ $tokens['gerant_identite'] ?: '—' }}</strong>,
            </li>
            <li>➤ Demeurant à <strong>{{ $tokens['gerant_adresse'] ?: '—' }}</strong></li>
        </ul>

        <p>
            Tout changement dans ces informations doit être signalé sans délai à
            <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> dans un délai d'(1) un mois.
            <span style="text-decoration: underline;">Il a été convenu et arrêté ce qui suit :</span><br>
            <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> fournit par le présent
            contrat au client qui accepte un ensemble de prestations de services décrit aux
            articles ci-après.
        </p>
    </div>

    {{-- ── DURATION & FINANCIAL SUMMARY (fixed clause, outside article library) ── --}}
    <div class="party-block">
        <p>
            Le présent contrat est prévu pour une durée de <strong>{{ $tokens['duree_mois'] ?: '—' }} mois</strong>
            qui commencera le <strong>{{ $tokens['date_debut'] ?: '—' }}</strong>
            et se terminera le <strong>{{ $tokens['date_fin'] ?: '—' }}</strong>.
            Le présent contrat est consenti moyennant une redevance mensuelle de
            <strong>{{ $tokens['redevance_mensuelle'] ?: '—' }}</strong>,
            soit <strong>{{ $tokens['redevance_annuelle'] ?: '—' }}</strong> annuelle
            @if ($tokens['mode_paiement'])
                , payable par <strong>{{ $tokens['mode_paiement'] }}</strong>
            @endif
            .
            @if ($tokens['caution'])
                Une caution de <strong>{{ $tokens['caution'] }}</strong> est exigée.
            @endif
        </p>
    </div>

    {{-- ── CLAUSES CONTRACTUELLES (dynamic article library) ───────────────── --}}
    @if ($articles && $articles->count() > 0)
        <div class="section-heading">Clauses Contractuelles</div>
        @foreach ($articles as $index => $article)
            <div class="article-block">
                <div class="article-title">Article {{ $index + 1 }} — {{ $article->title }}</div>
                {{-- body already escaped + token-resolved server-side — no e() here --}}
                <div class="article-body">{!! nl2br($article->body) !!}</div>
            </div>
        @endforeach
    @endif

    {{-- ── CERTIFICATION ────────────────────────────────────────────────── --}}
    <div class="party-block">
        <p>
            Je certifie, <strong>{{ $tokens['gerant_nom'] ?: '—' }}</strong>, l'exactitude des informations ci-dessous :<br>
            N° Tel : <strong>{{ $tokens['gerant_telephone'] ?: '—' }}</strong><br>
            Email : <strong>{{ $tokens['gerant_email'] ?: '—' }}</strong><br>
            Adresse personnelle : <strong>{{ $tokens['gerant_adresse'] ?: '—' }}</strong>
        </p>
    </div>

    {{-- ── SIGNATURES ───────────────────────────────────────────────────── --}}
    <div class="signature-section">
        @if ($tokens['ville_signature'] || $tokens['date_signature'])
            <div class="date-location">
                Fait à <strong>{{ $tokens['ville_signature'] ?: '___________' }}</strong>,
                le <strong>{{ $tokens['date_signature'] ?: '___________' }}</strong>
            </div>
        @endif

        <div class="signature-mention">« Signature précédée des mentions Lu et approuvé, bon pour accord »</div>

        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-label">La société {{ $tokens['domiciliataire_nom'] ?: '' }}</div>
                    <div class="signature-name">
                        Représentée par Mr. <strong>{{ $tokens['domiciliataire_representant'] ?: '' }}</strong>
                    </div>
                </td>
                <td>
                    <div class="signature-label">La société {{ $tokens['raison_sociale'] ?: '' }}</div>
                    <div class="signature-name">
                        Représentée par <strong>{{ $tokens['gerant_nom'] ?: '' }}</strong><br><br>
                        N° Tel : <strong>{{ $tokens['gerant_telephone'] ?: '—' }}</strong><br>
                        Email : <strong>{{ $tokens['gerant_email'] ?: '—' }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── FOOTER ───────────────────────────────────────────────────────── --}}
    <div class="footer">
        <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> —
        RC : <strong>{{ $tokens['domiciliataire_rc'] ?: '—' }}</strong> |
        IF : <strong>{{ $tokens['domiciliataire_if'] ?: '—' }}</strong> |
        TP : <strong>{{ $tokens['domiciliataire_tp'] ?: '—' }}</strong><br>
        {{ $tokens['domiciliataire_siege_succursales'] ?: '' }}<br>
        Généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</body>
</html>

