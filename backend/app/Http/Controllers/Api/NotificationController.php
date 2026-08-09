<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\DatabaseErrorHelper;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Lists system notifications for the authenticated user (excludes
    // direct messages, which have their own endpoint via MessageController).
    public function index()
    {
        $rows = AppNotification::query()
            ->where('user_id', auth()->id())
            ->whereNull('from_user_id')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // Marks a single notification as read.
    public function read(int $id)
    {
        $row = AppNotification::where('user_id', auth()->id())->findOrFail($id);
        $row->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true, 'data' => $row->fresh()]);
    }

    // Marks every unread system notification as read in one query.
    public function readAll()
    {
        AppNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->whereNull('from_user_id')
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    // Returns the user's expiry-alert delay preferences, defaulting to
    // 1 month if none are set or the stored JSON fails to decode.
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

    // Saves the user's chosen alert delays as sorted, deduplicated
    // months. Swallows only genuine "column doesn't exist" errors
    // (via DatabaseErrorHelper, which checks SQLSTATE codes so this
    // works correctly on both MySQL and PostgreSQL); all other DB
    // errors are surfaced as a real 500.
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
            if (!DatabaseErrorHelper::isUndefinedColumnError($e)) {
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