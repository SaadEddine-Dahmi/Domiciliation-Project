<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#1f2937; background:#f8f9fb; padding:24px;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
    <h2 style="color:#c8a96e;margin-top:0;">
      {{ $forDomiciliataire ? 'Contrat activé' : 'Votre contrat est actif ✅' }}
    </h2>

    @if($forDomiciliataire)
      <p>
        Le contrat <strong>{{ $contrat->titre_contrat ?: "#{$contrat->id}" }}</strong>
        pour <strong>{{ $entreprise->raison_sociale ?? 'votre client' }}</strong>
        a été légalisé (PDF signé importé) et est maintenant <strong>actif</strong>.
      </p>
    @else
      <p>Bonjour,</p>
      <p>
        Votre contrat <strong>{{ $contrat->titre_contrat ?: "#{$contrat->id}" }}</strong>
        avec <strong>{{ $domiciliataire->nom_societe ?? $domiciliataire->nom ?? 'votre domiciliataire' }}</strong>
        a été légalisé et est désormais actif.
      </p>
      <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
        <tr>
          <td style="padding:6px 0;color:#6b7280;">Date de début</td>
          <td style="padding:6px 0;text-align:right;">{{ optional($contrat->date_debut)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:#6b7280;">Date de fin</td>
          <td style="padding:6px 0;text-align:right;">{{ optional($contrat->date_fin)->format('d/m/Y') ?? '—' }}</td>
        </tr>
      </table>
    @endif

    <p style="font-size:12px;color:#9ca3af;margin-top:24px;">
      {{ $domiciliataire->nom_societe ?? config('app.name') }}
    </p>
  </div>
</body>
</html>