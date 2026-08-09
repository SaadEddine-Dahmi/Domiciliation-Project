<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomiciliaireProfile;
use Illuminate\Http\Request;

class DomiciliaireProfileController extends Controller
{
    // Returns the authenticated domiciliataire's company profile. The
    // profile relation may be null if it was never saved — every field
    // falls back to null/empty in that case rather than erroring.
    public function show()
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $profile = $user->profile;

        return response()->json([
            'success' => true,
            'data' => [
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'telephone' => $user->telephone,
                'nom_societe' => $profile?->nom_societe,
                'representant_legal' => $profile?->representant_legal,
                'identite_representant' => $profile?->identite_representant,
                'rc' => $profile?->rc,
                'if_fiscal' => $profile?->if_fiscal,
                'tp' => $profile?->tp,
                'adresses' => $profile?->adresses_list ?? [],
                'profile_complete' => $user->hasCompleteProfile(),
            ],
        ]);
    }

    // Saves the company profile. Contact fields (nom/prenom/telephone)
    // update the users row; everything else is upserted into
    // domiciliataire_profiles, created on first save.
    public function update(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:20'],
            'prenom' => ['nullable', 'string', 'max:20'],
            'telephone' => ['nullable', 'string', 'max:30'],

            'nom_societe' => ['nullable', 'string', 'max:255'],
            'representant_legal' => ['nullable', 'string', 'max:255'],
            'identite_representant' => ['nullable', 'string', 'max:100'],
            'rc' => ['nullable', 'string', 'max:100'],
            'if_fiscal' => ['nullable', 'string', 'max:100'],
            'tp' => ['nullable', 'string', 'max:100'],

            'adresses' => ['nullable', 'array'],
            'adresses.*.label' => ['required_with:adresses', 'string', 'max:100'],
            'adresses.*.value' => ['required_with:adresses', 'string', 'max:500'],
        ]);

        $userFields = array_intersect_key($data, array_flip(['nom', 'prenom', 'telephone']));
        if (!empty($userFields)) {
            $user->update($userFields);
        }

        $profileFields = array_diff_key($data, $userFields);

        DomiciliaireProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileFields
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'profile_complete' => $user->fresh()->hasCompleteProfile(),
        ]);
    }
}