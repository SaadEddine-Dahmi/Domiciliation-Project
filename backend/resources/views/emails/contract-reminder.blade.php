<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#1f2937; background:#f8f9fb; padding:24px;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
    <h2 style="color:#f59e0b;margin-top:0;">
      @switch($reminderType)
        @case('pre_expiry_30') Votre contrat expire dans 1 mois @break
        @case('pre_expiry_15') Votre contrat expire dans 15 jours @break
        @case('pre_expiry_3')  Votre contrat expire dans 3 jours @break
        @case('post_expiry')   Votre contrat a expiré @break
        @default Rappel concernant votre contrat
      @endswitch
    </h2>
    <p>Bonjour,</p>
    <p>
      Le contrat <strong>{{ $contrat->titre_contrat ?: "#{$contrat->id}" }}</strong>
      avec <strong>{{ $domiciliataire->nom_societe ?? $domiciliataire->nom ?? 'votre domiciliataire' }}</strong>
      @if($reminderType === 'post_expiry')
        a expiré le <strong>{{ optional($contrat->date_fin)->format('d/m/Y') }}</strong>.
      @else
        arrive à échéance le <strong>{{ optional($contrat->date_fin)->format('d/m/Y') }}</strong>.
      @endif
    </p>
    <p style="font-size:13px;color:#6b7280;">
      Contactez votre domiciliataire dès que possible pour organiser le renouvellement.
    </p>
  </div>
</body>
</html>