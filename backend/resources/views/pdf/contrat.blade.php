<!DOCTYPE html>
{{--
    resources/views/pdf/contrat.blade.php

    Contract PDF template rendered by dompdf via ContratController::generatePdf()
    and ContratController::streamPdf().

    DATA AVAILABLE IN THIS VIEW:
      $contrat   — Contrat model with eager-loaded:
                     → domiciliataire  (User model — the service provider)
                     → entreprise      (Entreprise model enriched with virtual
                                        attributes by prepareEntrepriseForPdf())
      $articles  — Collection of Article models with {{tokens}} already resolved
                   by resolveTokens() in ContratController BEFORE this view runs.

    VIRTUAL ATTRIBUTES ON $contrat->entreprise (injected by prepareEntrepriseForPdf):
      nom_societe    — raison_sociale (column name alias)
      gerant_prenom  — representant.prenom
      gerant_nom     — "PRÉNOM NOM" full name from representant
      gerant_cin     — representant.cin (CIN or passport)
      tel            — representant.telephone
      email          — representant.email
      adresse        — representant.adresse (personal address)
      date_naissance — representant.date_naissance formatted as d/m/Y
      nationalite    — representant.nationalite

    ADDRESS RENDERING (domiciliataire):
      $contrat->domiciliataire->adresses_list returns [{label, value}, ...]
      First entry  → siège principal
      Other entries → succursales
      These are rendered in D'UNE PART and in the fixed footer.

    ARTICLE BODIES:
      {!! nl2br(e($article->body)) !!}
      e() HTML-escapes the body string to prevent XSS.
      nl2br() converts newlines to <br> for paragraph-style articles.
      Tokens have already been resolved to their real values before Blade runs.
--}}
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $contrat->titre_contrat ?? 'Contrat de Domiciliation' }}</title>
    <style>
        /* ── Page margins ─────────────────────────────────────────────────── */
        /* Bottom margin is larger to leave space for the fixed footer. */
        @page {
            margin: 15mm 20mm 28mm 20mm;
        }

        /* ── Base typography ──────────────────────────────────────────────── */
        body {
            font-family: Arial, sans-serif;
            color: #000000;
            line-height: 1.5;
            font-size: 13px;
            margin: 0;
            padding: 0;
        }

        .page { width: 100%; }

        /* ── Contract title (centred, uppercase) ──────────────────────────── */
        .contract-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        /* ── Reference number line ────────────────────────────────────────── */

        .ref-line {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        /* ── Parties identification section ───────────────────────────────── */
        .parties-section { margin-bottom: 20px; }

        .party-block {
            margin-bottom: 14px;
            text-align: justify;
        }

        .party-title {
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 6px;
        }

        /* ── Client details bullet list ───────────────────────────────────── */
        .client-details-list {
            list-style: none;
            padding-left: 20px;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .client-details-list li { margin-bottom: 4px; }

        /* ── Section heading (CLAUSES CONTRACTUELLES) ─────────────────────── */
        .section-heading {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-top: 28px;
            margin-bottom: 12px;
        }

        /* ── Individual article block ─────────────────────────────────────── */
        /* page-break-inside: avoid keeps each article on one page when possible. */
        .article-block {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .article-title {
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .article-body { text-align: justify; }

        /* ── Signature section ────────────────────────────────────────────── */
        .signature-section {
            margin-top: 36px;
            page-break-inside: avoid;
        }

        .date-location {
            text-align: right;
            font-weight: bold;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .signature-mention {
            font-style: italic;
            text-align: center;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
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
            margin-bottom: 4px;
            font-size: 13px;
        }

        .signature-name { font-size: 12px; margin-top: 4px; }

        .signature-contact {
            font-size: 11px;
            margin-top: 8px;
            line-height: 1.5;
        }

        /*
         * ── Fixed footer ─────────────────────────────────────────────────────
         * position: fixed in dompdf renders the element on every page.
         * bottom: -15mm pulls it into the bottom margin zone.
         * The footer contains all addresses (siège + succursales) on line 1,
         * then RC/IF/TP on line 2 — exactly matching the base contract format.
         */
        .footer {
            position: fixed;
            bottom: -18mm;
            left: 0;
            right: 0;
            border-top: 1px solid #000000;
            padding-top: 4px;
            font-size: 10px;
            text-align: center;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<div class="page">

    {{-- ════════════════════════════════════════════════════════════════════════
         HEADER — Company name · contract title · optional reference number
    ════════════════════════════════════════════════════════════════════════ --}}

    {{-- Line 1: Domiciliataire company name --}}
    <div class="contract-title">
        {{ $contrat->domiciliataire->nom_societe ?? '—' }}
    </div>

    {{-- Line 2: Contract title — typed by domiciliataire in wizard step 1 --}}
    <div class="contract-title">
        {{ $contrat->titre_contrat ?? 'CONTRAT DE DOMICILIATION' }}
    </div>

    {{--
        Reference number line — ONLY rendered when instruction_no is set.
        Previous version always rendered "Réf. N° —" even when null.
    --}}
    @if ($contrat->instruction_no)
        <div class="ref-line">Réf. N° {{ $contrat->instruction_no }}</div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════
         PARTIES IDENTIFICATION
    ════════════════════════════════════════════════════════════════════════ --}}

    <div class="parties-section">
        <p style="font-weight:bold;text-decoration:underline;margin-bottom:10px;">
            Entre les soussignés :
        </p>

        {{-- ── D'UNE PART — The domiciliataire (service provider) ────────────────── --}}
        <div class="party-block">
            <div class="party-title">D'une part</div>
            <p>

                {{--
                    FIX: Full address chain from the adresses JSON array.
                    Previous version only showed ville_signature ("agadir").
                    Now shows: "Siège social : N° 78... – Succursale 1 : APPT N°4..."
                --}}
                @php
                    // adresses_list is a User accessor that safely returns []
                    // when the adresses column is null.
                    $domAdresses = $contrat->domiciliataire->adresses_list ?? [];
                @endphp

                Le Centre De domiciliation
                <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong>,
                RC <strong>{{ $contrat->domiciliataire->rc ?? '—' }}</strong>,
                I.F : <strong>{{ $contrat->domiciliataire->if_fiscal ?? '—' }}</strong>

                @if (!empty($domAdresses))
                    {{-- First address: always the siège principal --}}
                    sise
                    <strong>{{ $domAdresses[0]['label'] ?? 'Siège' }}</strong> :
                    <strong>{{ $domAdresses[0]['value'] ?? '' }}</strong>
                    {{-- Additional addresses: succursales --}}
                    @foreach (array_slice($domAdresses, 1) as $brIdx => $branch)
                        –
                        <strong>{{ $branch['label'] ?? ('Succursale ' . ($brIdx + 1)) }}</strong> :
                        <strong>{{ $branch['value'] ?? '' }}</strong>
                    @endforeach
                @else
                    {{-- Fallback when no addresses configured in profile --}}
                    sise à <strong>{{ $contrat->ville_signature ?? '—' }}</strong>
                @endif
                .
                <br>

                {{--
                    Domiciliataire email — FIX: was completely absent in previous version.
                    Only rendered when the email field is set on the User.
                --}}
                @if ($contrat->domiciliataire->email)
                    Email : <strong>{{ $contrat->domiciliataire->email }}</strong>.<br>
                @endif

                {{--
                    Représentant légal with CIN — previous version showed only the name.
                    Now also shows CIN (identite_representant) when set.
                --}}
                Représentée par
                <strong>{{ $contrat->domiciliataire->representant_legal ?? '—' }}</strong>
                @if ($contrat->domiciliataire->identite_representant)
                    titulaire de la CIN N°
                    <strong>{{ $contrat->domiciliataire->identite_representant }}</strong>
                @endif
                .
                <br>

                {{--
                    instruction_no in paragraph body — conditional.
                    Previous version always printed "suivant l'instruction No.: —".
                    Now: omits the instruction phrase entirely when the field is null.
                --}}
                @if ($contrat->instruction_no)
                    Déclare par la présente suivant l'instruction No. :
                    <strong>{{ $contrat->instruction_no }}</strong>, Donner domiciliation à :
                @else
                    Déclare par la présente Donner domiciliation à :
                @endif
            </p>
        </div>

        {{-- ── D'AUTRE PART — The client / domicilié ──────────────────────────────── --}}
        <div class="party-block">
            <div class="party-title">D'autre part</div>
            <p>
                {{-- Company name + domiciliation address (siège of the provider) --}}
                <strong>{{ $contrat->entreprise->nom_societe ?? '—' }}</strong>
                à l'adresse suivante C/O
                <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong>
                @if (!empty($domAdresses) && isset($domAdresses[0]['value']))
                    <strong>{{ $domAdresses[0]['value'] }}</strong>
                @else
                    <strong>{{ $contrat->ville_signature ?? '—' }}</strong>
                @endif
                .<br>

                Nous déclarons en outre avoir pris connaissance qu'en application
                des dispositions de l'article 93 du code de recouvrement des créances
                publiques, les rôles des impôts, états de produits et autres titres de
                perception régulièrement émis ont exécutions contre les redevables qui
                y sont inscrits, toutes autres personnes auprès desquelles les redevables
                ont élu domicile fiscal, avec leur accord.
            </p>

            <p style="margin-bottom:5px;">
                LA SOCIÉTÉ «
                <strong>{{ $contrat->entreprise->nom_societe ?? '—' }}</strong>
                » — Représentée par :
            </p>

            <ul class="client-details-list">
                {{--
                    Gérant identity: gerant_nom, date_naissance, gerant_cin.
                    All come from the representants table via prepareEntrepriseForPdf().
                    FIX: date_naissance and gerant_cin were absent in previous Blade.
                    Conditional rendering: each item is only shown when the data exists.
                --}}
                <li>
                    ➤
                    <strong>{{ $contrat->entreprise->gerant_nom ?: '—' }}</strong>
                    @if ($contrat->entreprise->date_naissance)
                        , né(e) le
                        <strong>{{ $contrat->entreprise->date_naissance }}</strong>
                    @endif
                    @if ($contrat->entreprise->gerant_cin)
                        , porteur de CIN/Passeport :
                        <strong>{{ $contrat->entreprise->gerant_cin }}</strong>
                    @endif
                </li>

                {{-- Personal address ("Demeurant à") --}}
                @if ($contrat->entreprise->adresse)
                    <li>
                        ➤ Demeurant à
                        <strong>{{ $contrat->entreprise->adresse }}</strong>
                    </li>
                @endif

                {{-- Nationality — FIX: was absent in previous Blade --}}
                @if ($contrat->entreprise->nationalite)
                    <li>
                        ➤ Nationalité :
                        <strong>{{ $contrat->entreprise->nationalite }}</strong>
                    </li>
                @endif
            </ul>

            <p>
                Tout changement dans ces informations doit être signalé sans délai à
                <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong>
                dans un délai d'(1) un mois.
                <span style="text-decoration:underline;">
                    Il a été convenu et arrêté ce qui suit :
                </span>
                <br>
                <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong>
                fournit par le présent contrat au client qui accepte un ensemble de
                prestations de services décrit aux articles ci-après.
            </p>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
         CLAUSES CONTRACTUELLES — dynamic articles selected by the domiciliataire

         Articles are rendered in the order set by drag-and-drop in wizard step 3.
         The $articles collection has already had its {{tokens}} resolved by
         ContratController::generatePdf() before this view was called.
         {!! nl2br(e($article->body)) !!} safely renders the pre-resolved text.
    ════════════════════════════════════════════════════════════════════════ --}}

    @if ($articles && $articles->count() > 0)
        <div class="section-heading">Clauses Contractuelles</div>

        @foreach ($articles as $index => $article)
            <div class="article-block">
                {{-- Article number is 1-based position in the sorted collection --}}
                <div class="article-title">
                    Article {{ $index + 1 }} — {{ $article->title }}
                </div>
                {{--
                    e() HTML-escapes the body (XSS prevention).
                    nl2br() converts newlines to <br> so paragraph breaks render.
                    Tokens are already resolved — no further substitution needed here.
                --}}
                <div class="article-body">
                    {!! nl2br(e($article->body)) !!}
                </div>
            </div>
        @endforeach
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════
         SIGNATURE BLOCK

         Left column:  domiciliataire (service provider)
         Right column: client company (gérant)
         Date/location line shown only when both fields are set.
    ════════════════════════════════════════════════════════════════════════ --}}

    <div class="signature-section">

        {{-- "Fait à VILLE, le DATE" — only when at least one is set --}}
        @if ($contrat->ville_signature || $contrat->date_signature)
            <div class="date-location">
                Fait à
                <strong>{{ $contrat->ville_signature ?? '___________' }}</strong>,
                le
                <strong>
                    {{ $contrat->date_signature
                        ? \Carbon\Carbon::parse($contrat->date_signature)->format('d/m/Y')
                        : '___________' }}
                </strong>
            </div>
        @endif

        <div class="signature-mention">
            « Signature précédée des mentions Lu et approuvé, bon pour accord »
        </div>

        <table class="signature-table">
            <tr>
                {{-- LEFT: Domiciliataire signature block --}}
                <td>
                    <div class="signature-label">
                        La société {{ $contrat->domiciliataire->nom_societe ?? '' }}
                    </div>
                    <div class="signature-name">
                        Représentée par Mr.
                        <strong>
                            {{ $contrat->domiciliataire->representant_legal ?? '' }}
                        </strong>
                    </div>
                </td>

                {{-- RIGHT: Client signature block --}}
                <td>
                    <div class="signature-label">
                        La société {{ $contrat->entreprise->nom_societe ?? '' }}
                    </div>
                    <div class="signature-name">
                        Représentée par
                        <strong>
                            {{ $contrat->entreprise->gerant_nom ?: '___________' }}
                        </strong>
                    </div>
                    {{--
                        Contact details below the signature.
                        tel and email come from prepareEntrepriseForPdf() (representants table).
                        Only shown when values are present.
                    --}}
                    <div class="signature-contact">
                        @if ($contrat->entreprise->tel)
                            N° Tel : <strong>{{ $contrat->entreprise->tel }}</strong><br>
                        @endif
                        @if ($contrat->entreprise->email)
                            Email : <strong>{{ $contrat->entreprise->email }}</strong>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
         FIXED FOOTER — appears on every page

         FIX: Previous version showed only "RC: X | IF: X | TP: X" and contract
         duration. Now renders the full address chain (siège + all succursales)
         on line 1, followed by company identifiers on line 2.

         This matches the base contract format:
           "Siège N° 78 KASBAR SOUSS KM5 BENSERGAO AGADIR –
            Succursale1 : APPT N°4 IMM 617... ; Succursale 2 : IMM 129 BUREAU 22...
            RC : 56989 IF :60102285 TP :55004406"
    ════════════════════════════════════════════════════════════════════════ --}}
    <div class="footer">

        {{-- Line 1: All addresses in order (siège then succursales) --}}
        @php
            $footerAdresses = $contrat->domiciliataire->adresses_list ?? [];
        @endphp

        @if (!empty($footerAdresses))
            {{-- First address with its label --}}
            <strong>{{ $footerAdresses[0]['label'] ?? 'Siège' }}</strong>
            {{ $footerAdresses[0]['value'] ?? '' }}

            {{-- Subsequent addresses separated by " – " --}}
            @foreach (array_slice($footerAdresses, 1) as $fIdx => $fAddr)
                –
                <strong>{{ $fAddr['label'] ?? ('Succursale ' . ($fIdx + 1)) }}</strong> :
                {{ $fAddr['value'] ?? '' }}
            @endforeach

            <br>
        @endif

        {{-- Line 2: Company identifiers --}}
        <strong>{{ $contrat->domiciliataire->nom_societe ?? '—' }}</strong>
        — RC : <strong>{{ $contrat->domiciliataire->rc ?? '—' }}</strong>
        | IF : <strong>{{ $contrat->domiciliataire->if_fiscal ?? '—' }}</strong>
        | TP : <strong>{{ $contrat->domiciliataire->tp ?? '—' }}</strong>
    </div>

</div>

</body>
</html>
