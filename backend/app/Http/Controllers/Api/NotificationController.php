<?php
// ============================================================
// app/Http/Controllers/Api/NotificationController.php
// - Notifications système (alertes contrats, paiements...)
// - Préférences de délai d'alerte (1, 3, 6 mois ou combinaison)
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Notifications système de l'utilisateur connecté */
    public function index()
    {
        $rows = AppNotification::query()
            ->where('user_id', auth()->id())
            ->whereNull('from_user_id')   // exclure les messages directs
            ->with('contrat:id,entreprise_id')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** Marquer une notification comme lue */
    public function read(int $id)
    {
        $row = AppNotification::where('user_id', auth()->id())->findOrFail($id);
        $row->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'data' => $row->fresh()]);
    }

    /** Marquer toutes les notifications comme lues */
    public function readAll()
    {
        AppNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->whereNull('from_user_id')
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues.']);
    }

    /** Récupère les préférences de délai d'alerte du domiciliataire */
    public function preferences()
    {
        $user = auth()->user();
        $prefs = json_decode($user->notification_preferences ?? '{"delays":[1]}', true);

        return response()->json(['success' => true, 'data' => $prefs]);
    }

    /**
     * Met à jour les préférences de délai d'alerte
     * delays: tableau de valeurs parmi [1, 3, 6]
     * Ex: [1, 3] = alertes à 1 mois ET 3 mois avant expiration
     */
    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'delays'   => ['required', 'array', 'min:1'],
            'delays.*' => ['integer', 'in:1,3,6'],
        ]);

        $user = auth()->user();
        $user->update([
            'notification_preferences' => json_encode(['delays' => array_unique($data['delays'])]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Préférences mises à jour.',
            'data'    => ['delays' => $data['delays']],
        ]);
    }
}
