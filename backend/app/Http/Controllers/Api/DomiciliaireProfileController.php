<?php
// app/Http/Controllers/Api/DomiciliaireProfileController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DomiciliaireProfileController extends Controller
{
    /**
     * GET /api/profile
     * Returns the full company profile of the authenticated domiciliataire.
     */
    public function show()
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'nom'                   => $user->nom,
                'prenom'                => $user->prenom,
                'telephone'             => $user->telephone,
                'nom_societe'           => $user->nom_societe,
                'representant_legal'    => $user->representant_legal,
                'identite_representant' => $user->identite_representant,
                'rc'                    => $user->rc,
                'if_fiscal'             => $user->if_fiscal,
                'tp'                    => $user->tp,
                // Array of {label: string, value: string}
                'adresses'              => $user->adresses_list,
                'profile_complete'      => $user->hasCompleteProfile(),
            ],
        ]);
    }

    /**
     * PUT /api/profile
     * Saves the company profile.
     * adresses is a JSON array: [{label:"Siège social", value:"123 Rue..."}, ...]
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:20'],
            'prenom' => ['nullable', 'string', 'max:20'],
            'telephone' => ['nullable', 'string', 'max:13'],
            'nom_societe' => ['nullable', 'string', 'max:255'],
            'representant_legal' => ['nullable', 'string', 'max:255'],
            'identite_representant' => ['nullable', 'string', 'max:100'],
            'rc' => ['nullable', 'string', 'max:100'],
            'if_fiscal' => ['nullable', 'string', 'max:100'],
            'tp' => ['nullable', 'string', 'max:100'],

            // Dynamic addresses array — any number of entries
            // Each entry must have a label AND a value
            'adresses' => ['nullable', 'array'],
            'adresses.*.label' => ['required_with:adresses', 'string', 'max:100'],
            'adresses.*.value' => ['required_with:adresses', 'string', 'max:500'],
        ]);

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'profile_complete' => $user->fresh()->hasCompleteProfile(),
        ]);
    }
}
