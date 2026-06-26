<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $contrat->titre_contrat ?? 'Contrat de Domiciliation' }}</title>
    <style>
        @page {
            margin: 15mm 20mm 20mm 20mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            color: #000000;
            line-height: 1.4;
            font-size: 14px;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 100%;
        }
        
        .contract-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        
        .ref-line {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 25px;
        }
        
        .parties-section {
            margin-bottom: 20px;
        }
        
        .party-block {
            margin-bottom: 15px;
            text-align: justify;
        }
        
        .party-title {
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 5px;
        }

        .client-details-list {
            list-style: none;
            padding-left: 30px;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .client-details-list li {
            margin-bottom: 3px;
        }
        
        .section-heading {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .article-block {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .article-title {
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .article-body {
            text-align: justify;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .date-location {
            text-align: right;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .signature-mention {
            font-style: italic;
            text-align: center;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
            height: 110px;
        }

        .signature-label {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
        }

        .signature-name {
            font-size: 13px;
            margin-top: 5px;
        }

        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 35px;
            border-top: 1px solid #000000;
            padding-top: 5px;
            font-size: 11px;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="page">

    {{-- ── HEADERS ────────────────────────────────────────────────────────── --}}
    <div class="contract-title">
        {{ $contrat->domiciliataire->nom_societe }}
    </div>
    <div class="contract-title">
        {{ $contrat->titre_contrat ?? 'CONTRAT DE DOMICILIATION' }}
    </div>

    @if ($contrat->instruction_no)
        <div class="ref-line">Réf. N° {{ $contrat->instruction_no }}</div>
    @endif

    {{-- ── PARTIES IDENTIFICATION ─────────────────────────────────────────── --}}
    <div class="parties-section">
        <p style="font-weight: bold; text-decoration: underline; margin-bottom: 10px;">Entre les soussignés :</p>
        
        <!-- D'une part -->
        <div class="party-block">
            <div class="party-title">D'une part</div>
            <p>
                Le Centre De domiciliation <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong>, RC <strong>{{ $contrat->domiciliataire->rc ?? '—' }}</strong> I.F: <strong>{{ $contrat->domiciliataire->if_fiscal ?? '—' }}</strong> sise à <strong>{{ $contrat->ville_signature ?? '—' }}</strong>. <br>
                Représentée par <strong>{{ $contrat->domiciliataire->representant_legal ?? '—' }}</strong>. <br>
                Déclare par la présente suivant l'instruction No.: <strong>{{ $contrat->instruction_no ?? '—' }}</strong>, Donner domiciliation à :
            </p>
        </div>

        <!-- D'autre part -->
        <div class="party-block">
            <div class="party-title">D'autre part</div>
            <p>
                <strong>{{ $contrat->entreprise->nom_societe ?? '—' }}</strong> » à l'adresse suivante C/O <strong>{{ $contrat->domiciliataire->nom_societe ?? 'AST-FISC' }}</strong> <strong>{{ $contrat->ville_signature ?? '—' }}</strong>.<br>
                Nous déclarons en outre avoir pris connaissance qu'en application des dispositions de l'article 93 du code de recouvrement des créances publiques, les rôles des impôts, états de produits et autres titres de perception régulièrement émis ont exécutions contre les redevables qui y sont inscrits, toutes autres personnes auprès desquelles les redevables ont élu domicile fiscal, avec leur accord.
            </p>
            
            <p style="margin-bottom: 5px;">LA SOCIETE « <strong>{{ $contrat->entreprise->nom_societe ?? '—' }}</strong>- Représentée par :</p>
            <ul class="client-details-list">
                <li>➤ <strong>{{ $contrat->entreprise->gerant_nom ?? '—' }}</strong>, porteur de CIN/Passeport : <strong>{{ $contrat->entreprise->gerant_cin ?? '—' }}</strong>,</li>
                <li>➤ Demeurant à <strong>{{ $contrat->entreprise->adresse ?? '—' }}</strong></li>
            </ul>
            
            <p>
                Tout changement dans ces informations doit être signalé sans délai à <strong>{{ $contrat->domiciliataire->nom_societe ?? 'AST-FISC' }}</strong> dans un délai d'(1) un mois. <span style="text-decoration: underline;">Il a été convenu et arrêté ce qui suit :</span><br>
                <strong>{{ $contrat->domiciliataire->nom_societe ?? 'AST-FISC' }}</strong> fournit par le présent contrat au client qui accepte un ensemble de prestations de services décrit aux articles ci-après.
            </p>
        </div>
    </div>

    {{-- ── CLAUSES CONTRACTUELLES (ARTICLES DYNAMIQUES) ────────────────────── --}}
    @if ($articles && $articles->count() > 0)
        <div class="section-heading">Clauses Contractuelles</div>

        @foreach ($articles as $index => $article)
            <div class="article-block">
                <div class="article-title">
                    Article {{ $index + 1 }} — {{ $article->title }}
                </div>
                <div class="article-body">
                    {!! nl2br(e($article->body)) !!}
                </div>
            </div>
        @endforeach
    @endif

    {{-- ── SIGNATURES SECTION ─────────────────────────────────────────────── --}}
    <div class="signature-section">
        @if ($contrat->ville_signature || $contrat->date_signature)
            <div class="date-location">
                Fait à <strong>{{ $contrat->ville_signature ?? '___________' }}</strong>, 
                le <strong>{{ $contrat->date_signature ? \Carbon\Carbon::parse($contrat->date_signature)->format('d/m/Y') : '___________' }}</strong>
            </div>
        @endif
        
        <div class="signature-mention">"Signature précédée des mentions Lu et approuvé, bon pour accord"</div>
        
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-label">La société {{ $contrat->domiciliataire->nom_societe ?? '' }}</div>
                    <div class="signature-name">Représentée par Mr. <strong>{{ $contrat->domiciliataire->representant_legal ?? '' }}</strong></div>
                </td>
                <td>
                    <div class="signature-label">La société {{ $contrat->entreprise->nom_societe ?? '' }}</div>
                    <div class="signature-name">Représentée par <strong>{{ $contrat->entreprise->gerant_nom ?? '' }}</strong></div>
                    <p style="font-size: 12px; margin-top: 10px; line-height: 1.3; font-weight: normal;">
                        N° Tel: <strong>{{ $contrat->entreprise->tel ?? '—' }}</strong><br>
                        Email: <strong>{{ $contrat->entreprise->email ?? '—' }}</strong>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── FOOTER CONFIGURATION ────────────────────────────────────────────── --}}
    <div class="footer">
        <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong> — RC: <strong>{{ $contrat->domiciliataire->rc ?? '—' }}</strong> | IF: <strong>{{ $contrat->domiciliataire->if_fiscal ?? '—' }}</strong> | TP: <strong>{{ $contrat->domiciliataire->tp ?? '—' }}</strong><br>
        Contrat établi pour une durée de <strong>{{ $contrat->duree_mois ?? '—' }} mois</strong> (Du {{ $contrat->date_debut ? \Carbon\Carbon::parse($contrat->date_debut)->format('d/m/Y') : '—' }} au {{ $contrat->date_fin ? \Carbon\Carbon::parse($contrat->date_fin)->format('d/m/Y') : '—' }})<br>
        @if($contrat->mode_paiement) — Mode de paiement: <strong>{{ $contrat->mode_paiement }}</strong> @endif — Généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</div>

</body>
</html>