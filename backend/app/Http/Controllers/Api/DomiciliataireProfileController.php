<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomiciliataireProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DomiciliataireProfileController extends Controller
{
    /**
     * GET /api/profile
     *
     * Company data (nom_societe, RC/IF/TP, adresses, contract_title)
     * comes from domiciliataire_profiles. The domiciliataire's own legal
     * representative — printed on the contract's "D'une part" block —
     * is now a proper polymorphic Representant record (see
     * User::representant() via HasRepresentant), managed through
     * DomiciliataireRepresentantController, not through this endpoint.
     */
    public function show()
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $profile = $user->profile;
        $rep = $user->representant;

        return response()->json([
            'success' => true,
            'data' => [
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'telephone' => $user->telephone,

                'photo_url' => $user->photo_url,
                'initials' => $user->initials,

                'nom_societe' => $profile?->nom_societe,

                // Heading printed at the top of the generated contract.
                // Nullable — the contract generator falls back to a
                // default title when this is empty.
                'contract_title' => $profile?->contract_title,

                'rc' => $profile?->rc,
                'if_fiscal' => $profile?->if_fiscal,
                'tp' => $profile?->tp,

                // Array of {label, value}. First entry is the siège
                // social; every entry after it a succursale — see
                // ContratController::buildTokenMap().
                'adresses' => $profile?->adresses_list ?? [],

                // The centre's own legal representative — same shape as
                // a client's representant. Null until first saved via
                // DomiciliataireRepresentantController::update().
                'representant' => $rep,

                'profile_complete' => $user->hasCompleteProfile(),
            ],
        ]);
    }

    /**
     * PUT /api/profile
     *
     * Only company-level fields — nom/prenom/telephone update the users
     * row directly; everything else is upserted into
     * domiciliataire_profiles. Representative identity (nom, cin, DOB,
     * nationality, contact) is NOT accepted here — see
     * DomiciliataireRepresentantController::update() instead.
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
            'telephone' => ['nullable', 'string', 'max:30'],

            'nom_societe' => ['nullable', 'string', 'max:255'],

            // Free text chosen by the domiciliataire — lets each centre
            // pick the title that appears on their own generated
            // contracts instead of a hardcoded string.
            'contract_title' => ['nullable', 'string', 'max:255'],

            'rc' => ['nullable', 'string', 'max:100'],
            'if_fiscal' => ['nullable', 'string', 'max:100'],
            'tp' => ['nullable', 'string', 'max:100'],

            // First address in the array = siège social.
            // Every subsequent address = a succursale.
            'adresses' => ['nullable', 'array'],
            'adresses.*.label' => ['required_with:adresses', 'string', 'max:100'],
            'adresses.*.value' => ['required_with:adresses', 'string', 'max:500'],
        ]);

        $userFields = array_intersect_key($data, array_flip(['nom', 'prenom', 'telephone']));
        if (!empty($userFields)) {
            $user->update($userFields);
        }

        $profileFields = array_diff_key($data, $userFields);

        if (!empty($profileFields)) {
            DomiciliataireProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileFields
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'profile_complete' => $user->fresh()->hasCompleteProfile(),
        ]);
    }

    // ── uploadPhoto(), deletePhoto(), photo() — unchanged from before ──
    // (identical to your current version, no changes related to this refactor)

    public function uploadPhoto(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $previousPath = $user->photo_path;
        $newPath = $request->file('photo')->store("profile-photos/{$user->id}", 'public');

        $user->update(['photo_path' => $newPath]);

        if ($previousPath && $previousPath !== $newPath) {
            Storage::disk('public')->delete($previousPath);
        }

        $fresh = $user->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil mise à jour.',
            'data' => [
                'photo_url' => $fresh->photo_url,
                'initials' => $fresh->initials,
            ],
        ]);
    }

    public function deletePhoto()
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->update(['photo_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil supprimée.',
            'data' => [
                'photo_url' => null,
                'initials' => $user->fresh()->initials,
            ],
        ]);
    }

    public function photo(int $userId)
    {
        $user = User::find($userId);

        if (!$user || !$user->photo_path || !Storage::disk('public')->exists($user->photo_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($user->photo_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}