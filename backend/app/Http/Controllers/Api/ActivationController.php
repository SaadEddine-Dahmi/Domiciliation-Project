<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivationController extends Controller
{
    public function __construct(private ActivationService $service) {}

    /**
     * GET /admin/users/pending
     * Lists all accounts waiting for approval.
     */
    public function pending(): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $users = User::where('status', 'pending')
            ->select('id', 'nom', 'prenom', 'email', 'telephone', 'role', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * POST /admin/users/{id}/approve
     * Approves an account with a future activation date.
     * Body: { "activation_date": "2026-04-10" }
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'activation_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $user = User::findOrFail($id);
        $this->service->approve($user, $request->activation_date);

        return response()->json(['success' => true, 'message' => 'Compte approuvé.']);
    }

    /**
     * POST /admin/users/{id}/reject
     * Rejects an account with a written reason.
     * Body: { "reason": "Dossier incomplet." }
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);
        $this->service->reject($user, $request->reason);

        return response()->json(['success' => true, 'message' => 'Compte rejeté.']);
    }
}
