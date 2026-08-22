<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use Illuminate\Http\Request;

class RepresentantController extends Controller
{
    /**
     * GET /entreprises/{entreprise}/representant
     * Returns the single representant — null if not created yet.
     */
    public function show(int $entrepriseId)
    {
        $entreprise = Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        return response()->json([
            'success' => true,
            'data' => $entreprise->representant,
        ]);
    }

    /**
     * POST /entreprises/{entreprise}/representant
     * Creates the representant — fails if one already exists.
     */
    public function store(Request $request, int $entrepriseId)
    {
        $entreprise = Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        if ($entreprise->representant()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette entreprise a déjà un représentant. Utilisez PUT pour modifier.',
            ], 422);
        }

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'cin' => ['required', 'string', 'max:50'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $rep = $entreprise->representant()->create($data);

        return response()->json(['success' => true, 'data' => $rep], 201);
    }

    /**
     * PUT /entreprises/{entreprise}/representant
     * Updates the single representant, or creates it if it doesn't exist yet.
     * Idempotent — the frontend doesn't need to know in advance whether
     * the representant already exists.
     */
    public function update(Request $request, int $entrepriseId)
    {
        $entreprise = Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'cin' => ['sometimes', 'string', 'max:50'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $rep = $entreprise->representant;

        if (!$rep) {
            if (empty($data['nom']) || empty($data['cin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nom et CIN sont requis pour créer le représentant.',
                ], 422);
            }

            $rep = $entreprise->representant()->create($data);

            return response()->json(['success' => true, 'data' => $rep], 201);
        }

        $rep->update($data);

        return response()->json(['success' => true, 'data' => $rep->fresh()]);
    }

    /**
     * DELETE /entreprises/{entreprise}/representant
     */
    public function destroy(int $entrepriseId)
    {
        $entreprise = Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        $entreprise->representant()->firstOrFail()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Représentant supprimé.',
        ]);
    }

    /**
     * GET /entreprises/{entreprise}/representant/history
     */
    public function history(int $entrepriseId)
    {
        $entreprise = Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        $rep = $entreprise->representant()->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $rep->history()->with('changedBy:id,nom,prenom')->get(),
        ]);
    }
}