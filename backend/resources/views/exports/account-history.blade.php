{{-- resources/views/exports/account-history.blade.php --}}
{{-- Self-contained HTML export of the domiciliataire's audit trail. --}}
{{-- Inline styles only — no external assets — so it opens correctly --}}
{{-- as a standalone downloaded file, with no network dependency. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des modifications</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #0e0e10; color: #e5e5e5; padding: 32px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        p.meta { color: #9a9a9a; font-size: 12px; margin-top: 0; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #2a2a2e; vertical-align: top; }
        th { color: #c8a96e; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: bold; }
        .pill-create { background: rgba(34,197,94,0.15); color: #22c55e; }
        .pill-update { background: rgba(234,179,8,0.15); color: #eab308; }
        .pill-delete { background: rgba(239,68,68,0.15); color: #ef4444; }
    </style>
</head>
<body>
    <h1>Historique des modifications</h1>
    <p class="meta">Généré le {{ $generatedAt->format('d/m/Y à H:i') }} · {{ $entries->count() }} entrée(s)</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Élément</th>
                <th>Action</th>
                <th>Champs modifiés</th>
                <th>Auteur</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ optional($entry['created_at'])->format('d/m/Y H:i') }}</td>
                    <td>{{ ucfirst($entry['type']) }}</td>
                    <td>{{ $entry['label'] }}</td>
                    <td><span class="pill pill-{{ $entry['action'] }}">{{ $entry['action'] }}</span></td>
                    <td>{{ is_array($entry['changed_fields']) ? implode(', ', $entry['changed_fields']) : '—' }}</td>
                    <td>{{ $entry['changed_by'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Aucune modification enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>