<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Entreprise;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Lists direct messages visible to the current user: sent OR received.
    public function index()
    {
        $user = auth()->user();

        $messages = AppNotification::query()
            ->whereNotNull('from_user_id')
            ->where(function ($query) use ($user) {
                $query->where('from_user_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->with([
                'fromUser:id,nom,prenom,email',
                'toUser:id,nom,prenom,email',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $messages]);
    }

    // Sends a direct message from a domiciliataire to one of their
    // clients. Verifies the target client actually belongs to this
    // tenant before allowing the send.
    public function send(Request $request)
    {
        $sender = auth()->user();

        if ($sender->role === 'client') {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'client_user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        $entreprise = Entreprise::where('domiciliataire_id', $sender->id)
            ->where('client_user_id', $data['client_user_id'])
            ->first();

        if (!$entreprise) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $notification = AppNotification::create([
            'user_id' => $data['client_user_id'],
            'from_user_id' => $sender->id,
            'message' => $data['message'],
            'subject' => $data['subject'] ?? null,
            'is_read' => false,
            'read_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $notification->load([
                'fromUser:id,nom,prenom,email',
                'toUser:id,nom,prenom,email',
            ]),
        ], 201);
    }

    // Marks a received message as read (client side).
    public function markRead(int $id)
    {
        $user = auth()->user();
        $notification = AppNotification::where('user_id', $user->id)->findOrFail($id);

        if (!$notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => $notification->fresh()]);
    }

    // Returns the read-receipt status of a sent message (domiciliataire side).
    public function receipt(int $id)
    {
        $sender = auth()->user();
        $notification = AppNotification::where('from_user_id', $sender->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => ['is_read' => $notification->is_read, 'read_at' => $notification->read_at],
        ]);
    }
}
