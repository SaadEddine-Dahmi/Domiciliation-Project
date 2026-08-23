<!-- resources/views/emails/document-uploaded.blade.php -->
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#1f2937; background:#f8f9fb; padding:24px;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
    <h2 style="color:#60a5fa;margin-top:0;">Nouveau document disponible 📄</h2>
    <p>Bonjour,</p>
    <p>
      Un nouveau document a été ajouté à votre espace
      @if($entreprise) pour <strong>{{ $entreprise->raison_sociale }}</strong> @endif :
    </p>
    <p style="background:#f3f4f6;border-radius:8px;padding:12px 16px;font-weight:600;">
      {{ $displayName }}
    </p>
    <p style="font-size:13px;color:#6b7280;">
      Connectez-vous à votre espace client pour le consulter ou le télécharger.
    </p>
  </div>
</body>
</html>