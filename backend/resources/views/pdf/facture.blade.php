<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    /* ── Reset ── */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
      font-size: 12px;
      color: #1a1d26;
      background: #fff;
      padding: 40px 48px;
    }

    /* ── Header ── */
    .header-table {
      width: 100%;
      border-bottom: 2px solid #c8a96e;
      padding-bottom: 20px;
      margin-bottom: 28px;
    }

    .logo-box {
      background: #c8a96e;
      color: #111;
      font-weight: 900;
      font-size: 18px;
      padding: 10px 16px;
      border-radius: 8px;
      display: inline-block;
    }

    .company-info {
      text-align: right;
      font-size: 11px;
      color: #555;
      line-height: 1.6;
    }

    /* ── Invoice badge ── */
    .invoice-badge-table {
      width: 100%;
      background: #f4f5f7;
      border-left: 4px solid #c8a96e;
      margin-bottom: 28px;
      border-radius: 0 8px 8px 0;
    }

    .invoice-badge-table td {
      padding: 16px 20px;
    }

    .invoice-badge-table .num {
      font-size: 20px;
      font-weight: 900;
      color: #c8a96e;
    }

    .invoice-badge-table .meta {
      text-align: right;
      font-size: 11px;
      color: #666;
    }

    /* ── Party Boxes ── */
    .party-box {
      background: #f9f9fb;
      border: 1px solid #e8eaed;
      border-radius: 8px;
      padding: 14px 16px;
      min-height: 100px;
    }

    .party-label {
      font-size: 9px;
      text-transform: uppercase;
      color: #c8a96e;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .party-name {
      font-size: 13px;
      font-weight: 700;
      color: #1a1d26;
      margin-bottom: 4px;
    }

    .party-detail {
      font-size: 10px;
      color: #666;
      line-height: 1.6;
    }

    /* ── Items table ── */
    .section-title {
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      color: #c8a96e;
      font-weight: 700;
      margin-bottom: 8px;
    }

    table.items {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    table.items th {
      background: #1a1d26;
      color: #fff;
      font-size: 10px;
      padding: 10px 12px;
      text-align: left;
    }

    table.items td {
      padding: 10px 12px;
      font-size: 11px;
      border-bottom: 1px solid #f0f1f4;
    }

    /* ── Totals ── */
    .totals-wrapper {
      width: 100%;
      margin-bottom: 28px;
    }

    .totals-table {
      width: 280px;
      float: right;
      border-collapse: collapse;
    }

    .totals-table td {
      padding: 6px 10px;
      font-size: 11px;
    }

    .grand-total td {
      background: #c8a96e;
      font-weight: 900;
      color: #111;
    }

    /* ── Status badge ── */
    .status-badge {
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 10px;
      font-weight: 700;
    }
    .status-paid { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef9c3; color: #854d0e; }

    /* ── Footer ── */
    .footer-table {
      width: 100%;
      border-top: 1px solid #e8eaed;
      margin-top: 20px;
      padding-top: 16px;
      font-size: 10px;
      color: #999;
    }
  </style>
</head>
<body>

  <!-- Header Section (Table Layout for dompdf) -->
  <table class="header-table">
    <tr>
      <td><div class="logo-box">AF</div></td>
      <td class="company-info">
        <strong>{{ $domiciliataire?->nom ?? 'AST-FISC SARL AU' }}</strong><br>
        {{ $domiciliataire?->email }}<br>
        <strong style="color:#c8a96e">AST-FISC Domiciliation</strong>
      </td>
    </tr>
  </table>

  <!-- Invoice Summary Bar -->
  <table class="invoice-badge-table">
    <tr>
      <td>
        <div style="font-size:10px; color:#888; text-transform:uppercase;">Facture</div>
        <div class="num">{{ $facture->numero_facture ?? ('FAC-' . $facture->id) }}</div>
      </td>
      <td class="meta">
        <span class="status-badge status-{{ $facture->statut }}">
          @if($facture->statut === 'paid') Payée
          @elseif($facture->statut === 'pending') En attente
          @else Annulée @endif
        </span><br>
        Date : {{ $facture->date_facture?->format('d/m/Y') ?? '—' }}
      </td>
    </tr>
  </table>

  <!-- Parties (Issuer & Client) -->
  <table style="width: 100%; border-spacing: 15px 0; margin-left: -15px; margin-bottom: 28px;">
    <tr>
      <td style="width: 50%;">
        <div class="party-box">
          <div class="party-label">Émetteur</div>
          <div class="party-name">{{ $domiciliataire?->nom ?? 'AST-FISC SARL AU' }}</div>
          <div class="party-detail">{{ $domiciliataire?->email }}</div>
        </div>
      </td>
      <td style="width: 50%;">
        <div class="party-box">
          <div class="party-label">Facturé à</div>
          <div class="party-name">{{ $entreprise?->raison_sociale ?? '—' }}</div>
          <div class="party-detail">
            {{ $entreprise?->adresse }}<br>
            {{ $entreprise?->ville }}
          </div>
        </div>
      </td>
    </tr>
  </table>

  <!-- Item Table -->
  <div class="section-title">Détail de la prestation</div>
  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th>Période du Contrat</th>
        <th style="text-align: right;">Montant</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Domiciliation commerciale - {{ $entreprise?->raison_sociale }}</td>
        <td>
          {{ $contrat?->date_debut?->format('d/m/Y') }}
          @if($contrat?->date_fin) au {{ $contrat->date_fin->format('d/m/Y') }} @endif
        </td>
        <td style="text-align: right;">{{ number_format((float)($facture->montant_total ?? 0), 2, ',', ' ') }} DH</td>
      </tr>
    </tbody>
  </table>

  <!-- Totals -->
  <div class="totals-wrapper">
    <table class="totals-table">
      <tr>
        <td style="color: #666;">Sous-total</td>
        <td style="text-align: right;">{{ number_format((float)($facture->montant_total ?? 0), 2, ',', ' ') }} DH</td>
      </tr>
      <tr>
        <td style="color: #666;">Total payé</td>
        <td style="text-align: right; color:#22c55e;">{{ number_format((float)$totalPaye, 2, ',', ' ') }} DH</td>
      </tr>
      @php $restant = max(0, (float)($facture->montant_total ?? 0) - (float)$totalPaye); @endphp
      @if($restant > 0)
      <tr>
        <td style="color: #666;">Restant dû</td>
        <td style="text-align: right; color:#ef4444;">{{ number_format($restant, 2, ',', ' ') }} DH</td>
      </tr>
      @endif
      <tr class="grand-total">
        <td style="font-weight: bold;">TOTAL À PAYER</td>
        <td style="text-align: right; font-weight: bold;">{{ number_format((float)($facture->montant_total ?? 0), 2, ',', ' ') }} DH</td>
      </tr>
    </table>
    <div style="clear: both;"></div>
  </div>

  <!-- Payments History -->
  @if($paiements->count() > 0)
    <div class="section-title">Historique des règlements</div>
    <table class="items">
      <thead>
        <tr>
          <th>Date</th>
          <th>Mode</th>
          <th style="text-align: right;">Montant</th>
        </tr>
      </thead>
      <tbody>
        @foreach($paiements as $p)
        <tr>
          <td>{{ $p->date_paiement?->format('d/m/Y') }}</td>
          <td style="text-transform: capitalize;">{{ $p->mode_paiement }}</td>
          <td style="text-align: right; font-weight: bold;">{{ number_format((float)$p->montant, 2, ',', ' ') }} DH</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <!-- Footer -->
  <table class="footer-table">
    <tr>
      <td>Généré le {{ now()->format('d/m/Y à H:i') }}</td>
      <td style="text-align: center; color: #c8a96e; font-weight: bold;">AST-FISC Domiciliation</td>
      <td style="text-align: right;">{{ $facture->numero_facture ?? '' }}</td>
    </tr>
  </table>

</body>
</html>
