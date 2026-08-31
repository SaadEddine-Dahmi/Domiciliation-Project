<?php
// app/Http/Controllers/Api/AccountHistoryController.php
// Exposes tenant-scoped account audit history and exports.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountHistoryService;
use Illuminate\Http\Request;

class AccountHistoryController extends Controller
{
    public function __construct(private readonly AccountHistoryService $history)
    {
    }

    public function index(Request $request)
    {
        $limit = min((int) $request->get('limit', 20), 200);

        return response()->json([
            'success' => true,
            'data' => $this->history->feed(auth()->id(), $limit),
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->get('format', 'json');
        abort_unless(in_array($format, ['json', 'html'], true), 422, 'Format invalide.');

        $tenantId = auth()->id();
        $entries = $this->history->feed($tenantId);
        $filename = 'historique-modifications-' . now()->format('Y-m-d') . '.' . $format;

        if ($format === 'html') {
            return response(view('exports.account-history', [
                'entries' => $entries,
                'generatedAt' => now(),
            ])->render(), 200, $this->downloadHeaders($filename, 'text/html; charset=UTF-8'));
        }

        return response(json_encode([
            'generated_at' => now()->toIso8601String(),
            'domiciliataire_id' => $tenantId,
            'total' => $entries->count(),
            'entries' => $entries->values(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, $this->downloadHeaders($filename, 'application/json; charset=UTF-8'));
    }

    private function downloadHeaders(string $filename, string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ];
    }
}
