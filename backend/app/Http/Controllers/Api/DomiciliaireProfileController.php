<?php
// ============================================================
// app/Http/Controllers/Api/DomiciliaireProfileController.php
//
// Profile management for the domiciliataire role.
//
// Routes:
//   GET /api/profile → show()   — returns all profile fields
//   PUT /api/profile → update() — saves all profile fields
//
// KEY FIXES vs previous version:
//   - show() now returns 'email'. Previously missing, which meant:
//       • The wizard step-1 summary never showed the domiciliataire's email.
//       • The {{domiciliataire_email}} PDF token always resolved to ''.
//       • The profile page could never pre-fill the email field.
//   - update() now validates and saves 'email' with a uniqueness rule that
//     correctly ignores the current user's own email (so saving without
//     changing the email doesn't trigger a "already taken" error).
//
// ADDRESS STRUCTURE:
//   adresses is stored as a JSON array in users.adresses (cast to array).
//   Each entry: {label: string, value: string}
//   First entry  → siège principal (primary address in PDF D'UNE PART)
//   Other entries → succursales (appended in PDF D'UNE PART and footer)
//   Example:
//     [
//       {"label": "Siège social", "value": "N° 78 KASBAR SOUSS KM5 BENSERGAO AGADIR"},
//       {"label": "Succursale 1", "value": "APPT N°4 IMM 617 AV MOHAMED EL FASSI..."},
//       {"label": "Succursale 2", "value": "IMM 129 BUREAU 22 ETAGE 2 HAUT FOUNTY AGADIR"}
//     ]
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomiciliaireProfileController extends Controller
{
    /**
     * GET /api/profile
     *
     * Returns the complete profile of the authenticated domiciliataire.
     *
     * All fields returned here are used by:
     *   1. The profile page (admin/profile.vue) to pre-fill the form.
     *   2. The contract wizard fillFromProfile() to autofill step-1 fields.
     *   3. ContratController::buildTokenMap() to resolve {{domiciliataire_*}} tokens.
     *
     * Only domiciliataire role can call this endpoint.
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
                // ── Personal identity of the domiciliataire person ────────────
                'nom'                   => $user->nom,
                'prenom'                => $user->prenom,
                'email'                 => $user->email,            // FIX: was missing
                'telephone'             => $user->telephone,

                // ── Company / société de domiciliation ────────────────────────
                'nom_societe'           => $user->nom_societe,
                'representant_legal'    => $user->representant_legal,
                'identite_representant' => $user->identite_representant,  // CIN du représentant
                'rc'                    => $user->rc,
                'if_fiscal'             => $user->if_fiscal,
                'tp'                    => $user->tp,

                // ── Addresses — JSON array of {label, value} objects ──────────
                // adresses_list is the User::getAdressesListAttribute() accessor
                // which safely returns [] when adresses is null.
                // First entry = siège; additional entries = succursales.
                'adresses'              => $user->adresses_list,

                // ── Completion flag ────────────────────────────────────────────
                // True when nom_societe, representant_legal, and at least one
                // address are all set. Used to show/hide the autofill banner in wizard.
                'profile_complete'      => $user->hasCompleteProfile(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PUT /api/profile
     *
     * Saves all profile fields for the authenticated domiciliataire.
     *
     * VALIDATION NOTES:
     *   - 'sometimes' on most fields: only validates/saves a field when it is
     *     present in the request. Allows partial updates without clearing fields.
     *   - email: Rule::unique()->ignore($user->id) allows saving the current
     *     user's own email without triggering a uniqueness conflict.
     *   - adresses: array of {label, value} objects. Entries with empty label
     *     or value are stripped by the frontend before the request is sent
     *     (profile.vue filterss them in saveProfile()).
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            // Personal contact
            'nom'                   => ['sometimes', 'string', 'max:20'],
            'prenom'                => ['nullable', 'string', 'max:20'],
            // FIX: email now accepted. Unique rule ignores the current user's own row.
            'email'                 => [
                'sometimes',
                'email',
                'max:100',
                Rule::unique('users')->ignore($user->id),
            ],
            'telephone'             => ['nullable', 'string', 'max:13'],

            // Company identity
            'nom_societe'           => ['nullable', 'string', 'max:255'],
            'representant_legal'    => ['nullable', 'string', 'max:255'],
            'identite_representant' => ['nullable', 'string', 'max:100'],
            'rc'                    => ['nullable', 'string', 'max:100'],
            'if_fiscal'             => ['nullable', 'string', 'max:100'],
            'tp'                    => ['nullable', 'string', 'max:100'],

            // Addresses: siège + succursales
            // Each entry must have both label and value non-empty.
            'adresses'              => ['nullable', 'array'],
            'adresses.*.label'      => ['required_with:adresses', 'string', 'max:100'],
            'adresses.*.value'      => ['required_with:adresses', 'string', 'max:500'],
        ]);

        $user->update($data);

        return response()->json([
            'success'          => true,
            'message'          => 'Profil mis à jour.',
            'profile_complete' => $user->fresh()->hasCompleteProfile(),
        ]);
    }
}
