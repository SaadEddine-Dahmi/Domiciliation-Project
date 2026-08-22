<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DomiciliataireRepresentantController extends Controller
{
    /**
     * GET /api/profile/representant
     * Returns the authenticated domiciliataire's own legal representative —
     * null if not created yet.
     */
    public function show()
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $user->representant,
        ]);
    }

    /**
     * PUT /api/profile/representant
     * Creates or updates the domiciliataire's own legal representative.
     * Idempotent, mirroring RepresentantController::update() on the
     * client side.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'nom'            => ['sometimes', 'string', 'max:100'],
            'prenom'         => ['nullable', 'string', 'max:100'],
            'cin'            => ['sometimes', 'string', 'max:50'],
            'nationalite'    => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse'        => ['nullable', 'string'],
            'telephone'      => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
        ]);

        $rep = $user->representant;

        if (!$rep) {
            if (empty($data['nom']) || empty($data['cin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nom et CIN sont requis pour créer le représentant.',
                ], 422);
            }

            $rep = $user->representant()->create($data);

            return response()->json(['success' => true, 'data' => $rep], 201);
        }

        $rep->update($data);

        return response()->json(['success' => true, 'data' => $rep->fresh()]);
    }
}