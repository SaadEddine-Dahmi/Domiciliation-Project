<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Notifications système (sans messages directs) */
    public function index()
    {
        $rows = AppNotification::query()
            ->where('user_id', auth()->id())
            ->whereNull('from_user_id')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function read(int $id)
    {
        $row = AppNotification::where('user_id', auth()->id())->findOrFail($id);
        $row->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true, 'data' => $row->fresh()]);
    }

    public function readAll()
    {
        AppNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->whereNull('from_user_id')
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Préférences : lit depuis JSON en DB
     * Si colonne absente, retourne défaut [1]
     */
    public function preferences()
    {
        $user = auth()->user();

        try {
            $raw   = $user->notification_preferences;
            $prefs = $raw ? json_decode($raw, true) : ['delays' => [1]];
        } catch (\Throwable $e) {
            $prefs = ['delays' => [1]];
        }

        return response()->json(['success' => true, 'data' => $prefs]);
    }

    /**
     * Sauvegarde les délais choisis
     * Crée la colonne si elle n'existe pas encore (fallback silencieux)
     */
    public function updatePreferences(Request $request)
{
    $data = $request->validate([
        'delays'   => ['required', 'array'],
        'delays.*' => ['integer', 'min:1', 'max:24'],
    ]);

    $delays = array_values(array_unique($data['delays']));
    sort($delays);

    try {
        auth()->user()->update([
            'notification_preferences' => json_encode(['delays' => $delays]),
        ]);
    } catch (\Throwable $e) {
        // FIX: only swallow the specific "unknown column" error that occurs
        // when the migration hasn't run yet. All other DB errors are real
        // failures and must be returned to the client.
        $isUnknownColumn = str_contains($e->getMessage(), 'notification_preferences')
            && str_contains($e->getMessage(), 'Unknown column');

        if (!$isUnknownColumn) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde des préférences.',
            ], 500);
        }

    }

    return response()->json([
        'success' => true,
        'data'    => ['delays' => $delays],
    ]);
}
}
