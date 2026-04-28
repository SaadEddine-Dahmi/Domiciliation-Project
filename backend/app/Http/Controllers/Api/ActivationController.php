<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Http\Request;

class ActivationController extends Controller
{
    public function __construct(private ActivationService $service) {}

    public function pending()
    {
        // Guard: only admin can call this
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $users = User::where('status', 'pending')
            ->where('role', 'domiciliataire')
            ->latest()
            ->get(['id', 'nom', 'prenom', 'email', 'telephone', 'created_at']);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function approve(Request $request, int $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'activation_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $user = User::findOrFail($id);

        // FIX: guard — only pending domiciliataires can be approved
        if ($user->status !== 'pending' || $user->role !== 'domiciliataire') {
            return response()->json([
                'message' => 'Ce compte ne peut pas être approuvé. Statut actuel : ' . $user->status,
            ], 422);
        }

        $this->service->approve($user, $request->activation_date);

        return response()->json(['success' => true, 'message' => 'Compte approuvé.']);
    }

    public function reject(Request $request, int $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);

        // FIX: guard — only pending domiciliataires can be rejected
        if ($user->status !== 'pending' || $user->role !== 'domiciliataire') {
            return response()->json([
                'message' => 'Ce compte ne peut pas être rejeté. Statut actuel : ' . $user->status,
            ], 422);
        }

        $this->service->reject($user, $request->reason);

        return response()->json(['success' => true, 'message' => 'Compte rejeté.']);
    }
}
