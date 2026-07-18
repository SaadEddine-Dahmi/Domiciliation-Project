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
            'data'    => $rep,
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

        // Enforce 1-to-1 at application level as well
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
     * Updates the single representant — no {id} needed.
     */
public function update(Request $request, int $entrepriseId)
{
    Entreprise::where('domiciliataire_id', auth()->id())
        ->findOrFail($entrepriseId);

    $rep = Representant::where('entreprise_id', $entrepriseId)
        ->firstOrFail();

    // FIX: use 'sometimes' instead of 'required' so frontend can send
    // partial payloads (Partial<Representant>) without triggering 422s.
    // Fields not present in the request are simply not updated.
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
}
