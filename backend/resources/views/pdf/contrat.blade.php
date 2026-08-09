<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $contrat->titre_contrat ?? 'Contrat de Domiciliation' }}</title>
    <style>
        @page { margin: 15mm 20mm 20mm 20mm; }
        body { font-family: Arial, sans-serif; color: #000; line-height: 1.4; font-size: 13px; margin: 0; padding: 0; }
        .page { width: 100%; }
        .contract-title { text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; margin: 10px 0 5px; }
        .ref-line { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 25px; }
        .parties-section { margin-bottom: 20px; }
        .party-block { margin-bottom: 15px; text-align: justify; }
        .party-title { font-weight: bold; text-transform: uppercase; text-decoration: underline; margin-bottom: 5px; }
        .client-details-list { list-style: none; padding-left: 30px; margin: 0 0 10px; }
        .client-details-list li { margin-bottom: 3px; }
        .section-heading { font-size: 15px; font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 30px 0 15px; }
        .article-block { margin-bottom: 15px; page-break-inside: avoid; }
        .article-title { font-weight: bold; font-size: 14px; text-transform: uppercase; margin-bottom: 4px; }
        .article-body { text-align: justify; }
        /* Article bodies arrive pre-wrapped: resolved tokens in <strong>,
           unresolved ones visibly marked in <em> — see resolveArticleBody() */
        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .date-location { text-align: right; font-weight: bold; margin-bottom: 15px; font-size: 13px; }
        .signature-mention { font-style: italic; text-align: center; margin-bottom: 15px; font-size: 13px; }
        .signature-table { width: 100%; border-collapse: collapse; }
        .signature-table td { width: 50%; vertical-align: top; padding: 10px; height: 110px; }
        .signature-label { font-weight: bold; text-decoration: underline; margin-bottom: 5px; }
        .signature-name { font-size: 13px; margin-top: 5px; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; min-height: 35px; border-top: 1px solid #000; padding-top: 5px; font-size: 10px; text-align: center; line-height: 1.4; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="contract-title">{{ $tokens['domiciliataire_nom'] ?: '—' }}</div>
    <div class="contract-title">{{ $contrat->titre_contrat ?? 'CONTRAT DE DOMICILIATION' }}</div>
    @if ($tokens['instruction_no'])
        <div class="ref-line">Réf. N° {{ $tokens['instruction_no'] }}</div>
    @endif

    {{-- Parties --}}
    <div class="parties-section">
        <p style="font-weight: bold; text-decoration: underline; margin-bottom: 10px;">Entre les soussignés :</p>

        <div class="party-block">
            <div class="party-title">D'une part</div>
            <p>
                Le Centre De domiciliation <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong>,
                RC <strong>{{ $tokens['domiciliataire_rc'] ?: '—' }}</strong>,
                I.F : <strong>{{ $tokens['domiciliataire_if'] ?: '—' }}</strong>
                sise à <strong>{{ $tokens['domiciliataire_adresse'] ?: '—' }}</strong>.<br>
                Représentée par <strong>{{ $tokens['domiciliataire_representant'] ?: '—' }}</strong>
                @if ($tokens['domiciliataire_identite_representant'])
                    titulaire de la CIN N° <strong>{{ $tokens['domiciliataire_identite_representant'] }}</strong>
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

        <div class="party-block">
            <div class="party-title">D'autre part</div>
            <p>
                <strong>{{ $tokens['raison_sociale'] ?: '—' }}</strong> à l'adresse suivante
                C/O <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong>
                <strong>{{ $tokens['ville_client'] ?: ($tokens['ville_signature'] ?: '—') }}</strong>.<br>
                Nous déclarons en outre avoir pris connaissance qu'en application des dispositions de l'article 93 du code de recouvrement des créances publiques, les rôles des impôts, états de produits et autres titres de perception régulièrement émis ont exécutions contre les redevables qui y sont inscrits, toutes autres personnes auprès desquelles les redevables ont élu domicile fiscal, avec leur accord.
            </p>

            <p style="margin-bottom: 5px;">LA SOCIETE « <strong>{{ $tokens['raison_sociale'] ?: '—' }}</strong> » — Représentée par :</p>
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
                <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> fournit par le présent contrat au client qui accepte un ensemble de prestations de services décrit aux articles ci-après.
            </p>
        </div>
    </div>

    {{-- Durée et redevance (bloc fixe, hors bibliothèque d'articles) --}}
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

    {{-- Clauses (articles dynamiques, déjà résolus par resolveArticleBody()) --}}
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

    {{-- Certification du gérant --}}
    <div class="party-block">
        <p>
            Je certifie, <strong>{{ $tokens['gerant_nom'] ?: '—' }}</strong>, l'exactitude des informations ci-dessous :<br>
            N° Tel : <strong>{{ $tokens['gerant_telephone'] ?: '—' }}</strong><br>
            Email : <strong>{{ $tokens['gerant_email'] ?: '—' }}</strong><br>
            Adresse personnelle : <strong>{{ $tokens['gerant_adresse'] ?: '—' }}</strong>
        </p>
    </div>

    {{-- Signatures --}}
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
                    <div class="signature-name">Représentée par Mr. <strong>{{ $tokens['domiciliataire_representant'] ?: '' }}</strong></div>
                </td>
                <td>
                    <div class="signature-label">La société {{ $tokens['raison_sociale'] ?: '' }}</div>
                    <div class="signature-name">Représentée par <strong>{{ $tokens['gerant_nom'] ?: '' }}</strong></div>
                    <p style="font-size: 12px; margin-top: 10px; line-height: 1.3; font-weight: normal;">
                        N° Tel : <strong>{{ $tokens['gerant_telephone'] ?: '—' }}</strong><br>
                        Email : <strong>{{ $tokens['gerant_email'] ?: '—' }}</strong>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <strong>{{ $tokens['domiciliataire_nom'] ?: '—' }}</strong> —
        RC : <strong>{{ $tokens['domiciliataire_rc'] ?: '—' }}</strong> |
        IF : <strong>{{ $tokens['domiciliataire_if'] ?: '—' }}</strong> |
        TP : <strong>{{ $tokens['domiciliataire_tp'] ?: '—' }}</strong><br>
        {{ $tokens['domiciliataire_adresse'] ?: '' }}<br>
        Généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</div>
</body>
</html>
