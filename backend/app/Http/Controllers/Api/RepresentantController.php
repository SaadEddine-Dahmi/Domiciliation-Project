<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Representant;
use Illuminate\Http\Request;

class RepresentantController extends Controller
{
    /**
     * GET /entreprises/{entreprise}/representant
     * Returns the single representant — null if not created yet.
     */
    public function show(int $entrepriseId)
    {
        Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        $rep = Representant::where('entreprise_id', $entrepriseId)->first();

        return response()->json([
            'success' => true,
            'data' => $rep,
        ]);
    }

    /**
     * POST /entreprises/{entreprise}/representant
     * Creates the representant — fails if one already exists.
     */
    public function store(Request $request, int $entrepriseId)
    {
        Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        if (Representant::where('entreprise_id', $entrepriseId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette entreprise a déjà un représentant. Utilisez PUT pour modifier.',
            ], 422);
        }

        $data = $request->validate([
            'nom'            => ['required', 'string', 'max:100'],
            'prenom'         => ['nullable', 'string', 'max:100'],
            'cin'            => ['required', 'string', 'max:50'],
            'nationalite'    => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse'        => ['nullable', 'string'],
            'telephone'      => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
        ]);

        $rep = Representant::create([
            ...$data,
            'entreprise_id' => $entrepriseId,
        ]);

        return response()->json(['success' => true, 'data' => $rep], 201);
    }

    /**
     * PUT /entreprises/{entreprise}/representant
     * Updates the single representant, or creates it if it doesn't exist yet.
     *
     * FIX: this used to call firstOrFail(), which threw a 404
     * ("No query results for model [App\Models\Representant]") the moment
     * you PUT before a representant row exists — e.g. right after creating
     * a new client, before its representant has ever been saved.
     * PUT is now idempotent: it creates the record on first save and
     * updates it on every save after that, so the frontend doesn't need
     * to know in advance whether the representant already exists.
     *
     * When creating implicitly through PUT, 'nom' and 'cin' become
     * mandatory even though they are 'sometimes' below (which allows
     * partial updates once the record exists).
     */
    public function update(Request $request, int $entrepriseId)
    {
        Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

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

        $rep = Representant::where('entreprise_id', $entrepriseId)->first();

        if (!$rep) {
            if (empty($data['nom']) || empty($data['cin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nom et CIN sont requis pour créer le représentant.',
                ], 422);
            }

            $rep = Representant::create([
                ...$data,
                'entreprise_id' => $entrepriseId,
            ]);

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
        Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        Representant::where('entreprise_id', $entrepriseId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Représentant supprimé.',
        ]);
    }

    /**
     * GET /entreprises/{entreprise}/representant/history
     * Returns the audit trail for this entreprise's representant, newest first.
     */
    public function history(int $entrepriseId)
    {
        Entreprise::where('domiciliataire_id', auth()->id())
            ->findOrFail($entrepriseId);

        $rep = Representant::where('entreprise_id', $entrepriseId)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $rep->history()->with('changedBy:id,nom,prenom')->get(),
        ]);
    }
}