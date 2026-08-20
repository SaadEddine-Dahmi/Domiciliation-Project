{{--
    resources/views/pdf/contrat.blade.php
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $contrat->titre_contrat ?: 'Contrat de Domiciliation' }}</title>
    <style>
        @page {
            size: A4;
            margin: 12mm 15mm 12mm 15mm;
        }
        * { box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            color: #000;
            font-size: 9.5pt;
            line-height: 1.25;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* --- DOMPDF FULL-PAGE OUTER BORDER FRAME --- */
        .page-border-frame {
            position: fixed;
            top: -2mm;
            left: -2mm;
            right: -2mm;
            bottom: -2mm;
            border: 1.5px solid #000;
            pointer-events: none;
            z-index: 9999;
        }

        .content-container {
            padding: 10px 12px 0 12px;
        }

        .brand-header {
            text-align: center;
            font-size: 30pt;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .contract-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 6px 0 80px 0;
            letter-spacing: 0.5px;
        }

        .party-title {
            font-weight: bold;
            text-decoration: underline;
            font-size: 10pt;
            margin-top: 6px;
            margin-bottom: 2px;
        }

        .party-block {
            text-align: justify;
            margin-bottom: 6px;
        }

        .bullet-list {
            list-style: none;
            padding-left: 20px;
            margin: 2px 0 3px 0;
        }
        .bullet-list li {
            margin-bottom: 2px;
            position: relative;
            padding-left: 15px;
        }
        .bullet-list li::before {
            content: ">";
            font-weight: bold;
            position: absolute;
            left: 0;
            top: 0;
        }

        .article-block {
            margin-top: 8px;
            margin-bottom: 8px;
            text-align: justify;
        }

        .article-title {
            font-weight: bold;
            font-size: 9.5pt;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 2px;
            display: block;
        }

        .article-body {
            text-align: justify;
        }

        .u-line {
            text-decoration: underline;
        }

        .signature-section {
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .date-location {
            text-align: right;
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 9.5pt;
        }

        .signature-mention {
            font-style: italic;
            text-align: center;
            margin-bottom: 8px;
            font-size: 9pt;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 5px;
        }

        .signature-label {
            font-weight: bold;
            font-size: 9.5pt;
            margin-bottom: 2px;
        }

        /* --- FIXED FOOTER INSIDE THE PAGE BOTTOM --- */
        .footer {
            position: fixed;
            bottom: 4mm;
            left: 10mm;
            right: 10mm;
            font-size: 8pt;
            text-align: center;
            line-height: 1.2;
            border-top: 1px solid #000;
            padding-top: 3px;
        }

        strong { font-weight: bold; }
    </style>
</head>
<body>

    {{-- FIXED BORDER FRAME DRAWN OVER THE ENTIRE PAGE --}}
    <div class="page-border-frame"></div>

    <div class="content-container">

        <div class="brand-header">
            {{ $tokens['domiciliataire_nom'] }}
        </div>

        <div class="contract-title">
            {{ $contrat->titre_contrat ?: 'CONTRAT DE DOMICILIATION' }}
        </div>

        <div class="party-title">D'une part</div>
        <div class="party-block">
            Entre les soussignés :<br>
            Centre De domiciliation <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> ,RC <strong>{{ $tokens['domiciliataire_rc'] ?: '—' }}</strong> .I.F : <strong>{{ $tokens['domiciliataire_if'] ?: '—' }}</strong> sise à <strong>{{ $tokens['domiciliataire_siege_succursales'] ?: '—' }}</strong>. Représentée par <strong>{{ $tokens['domiciliataire_representant'] ?: '—' }}</strong> titulaire de la CIN N° <strong>{{ $tokens['domiciliataire_identite_representant'] ?: '—' }}</strong>.<br>
            Déclare par la présente 
            @if (!empty($tokens['instruction_no']))
                suivant l'instruction No. : <strong>{{ $tokens['instruction_no'] }}</strong>,
            @endif
            Donner domiciliation à
        </div>

        <div class="party-title">D'autre part</div>
        <div class="party-block">
            <strong>{{ $tokens['raison_sociale'] ?: '—' }}</strong> » à l'adresse suivante C/O <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> <strong>{{ $tokens['domiciliataire_siege_succursales'] ?: '—' }}</strong>.<br>
            Nous déclarons en outre avoir pris connaissance qu'en application des dispositions de l'article 93 du code de recouvrement des créances publiques, les rôles des impôts, états de produits et autres titres de perception régulièrement émis ont exécutions contre les redevables qui y sont inscrits, toutes autres personnes auprès desquelles les redevables ont élu domicile fiscal, avec leur accord.<br>
            LA SOCIETE « <strong>{{ $tokens['raison_sociale'] ?: '—' }}</strong>- Représentée par :
            <ul class="bullet-list">
                <li><strong>{{ $tokens['gerant_nom'] ?: '—' }}</strong>@if(!empty($tokens['date_naissance'])), né le <strong>{{ $tokens['date_naissance'] }}</strong>@endif, porteur de CIN/Passeport : <strong>{{ $tokens['gerant_identite'] ?: '—' }}</strong>,</li>
                <li>Demeurant à <strong>{{ $tokens['gerant_adresse'] ?: '—' }}</strong></li>
            </ul>
            Tout changement dans ces informations doit être signalé sans délai à <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> dans un délai d'(1) un mois. <span class="u-line">Il a été convenu et arrêté ce qui suit :</span><br>
            <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> fournit par le présent contrat au client qui accepte un ensemble de prestations de services décrit aux articles ci-après.
        </div>

        <div class="article-block">
            <div class="article-title">ARTICLE 1 : DUREE</div>
            <div class="article-body">
                Le présent contrat est prévu pour une durée de <strong>{{ $tokens['duree_mois'] ?: '12' }} Mois</strong> qui commencera le <strong>{{ $tokens['date_debut'] ?: '—' }}</strong> et se terminera le <strong>{{ $tokens['date_fin'] ?: '—' }}</strong>.<br>
                Les deux parties pourront résilier le présent contrat par lettre recommandée avec accusé de réception, en respectant le préavis de (1) mois.<br>
                Avant expiration de la durée du préavis, le client devra justifier auprès du <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> soit de son transfert de siège social soit de la dissolution de son entreprise par la remise d'un extrait de registre du commerce modificatif, A défaut, les honoraires resteront dus jusqu'à justification.
            </div>
        </div>

        @if (!empty($articles) && $articles->count() > 0)
            @foreach ($articles as $index => $article)
                <div class="article-block">
                    <div class="article-title">ARTICLE {{ $index + 2 }} : {{ mb_strtoupper($article->title) }}</div>
                    <div class="article-body">{!! nl2br($article->body) !!}</div>
                </div>
            @endforeach
        @endif

        <div class="article-block">
            <div class="article-title">ARTICLE {{ (!empty($articles) ? $articles->count() : 0) + 2 }} : REDEVANCE</div>
            <div class="article-body">
                Le présent contrat est consenti moyennant une redevance mensuelle de <strong>{{ $tokens['redevance_mensuelle'] ?: '—' }}</strong>, soit <strong>{{ $tokens['redevance_annuelle'] ?: '—' }}</strong> Annuelle payable d'avance.
            </div>
        </div>

        <div class="article-block">
            <div class="article-title">ARTICLE {{ (!empty($articles) ? $articles->count() : 0) + 3 }} : CONTACT</div>
            <div class="article-body">
                Je certifie, <strong>{{ $tokens['gerant_nom'] ?: '—' }}</strong> l'exactitude des informations ci-dessous :<br>
                N° Tel : <strong>{{ $tokens['gerant_telephone'] ?: '—' }}</strong><br>
                Email : <strong>{{ $tokens['gerant_email'] ?: '—' }}</strong><br>
                Adresse personnelle : <strong>{{ $tokens['gerant_adresse'] ?: '—' }}</strong>
            </div>
        </div>

        <div class="signature-section">
            <div class="date-location">
                <strong>{{ $tokens['ville_signature'] ?: 'AGADIR' }}</strong>, le <strong>{{ $tokens['date_signature'] ?: now()->format('d/m/Y') }}</strong>
            </div>

            <div class="signature-mention">« Signature précédée des mentions Lu et approuvé, bon pour accord »</div>

            <table class="signature-table">
                <tr>
                    <td>
                        <div class="signature-label">La société {{ $tokens['domiciliataire_nom'] ?: '—' }}</div>
                        <div>Représentée par Mr. <strong>{{ $tokens['domiciliataire_representant'] ?: '—' }}</strong></div>
                    </td>
                    <td>
                        <div class="signature-label">La société {{ $tokens['raison_sociale'] ?: '—' }}</div>
                        <div>Représentée par <strong>{{ $tokens['gerant_nom'] ?: '—' }}</strong></div>
                    </td>
                </tr>
            </table>
        </div>

    </div>

    {{-- FOOTER POSITIONED SAFELY ABOVE THE BOTTOM BORDER LINE --}}
    <div class="footer">
        {{ $tokens['domiciliataire_siege_succursales'] ?: '' }}<br>
        RC: {{ $tokens['domiciliataire_rc'] ?: '—' }} IF: {{ $tokens['domiciliataire_if'] ?: '—' }} TP: {{ $tokens['domiciliataire_tp'] ?: '—' }}
    </div>

</body>
</html>