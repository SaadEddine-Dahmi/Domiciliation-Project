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

        if (!in_array($user->role, ['domiciliataire', 'admin'], true)) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $isDomiciliataire = $user->role === 'domiciliataire';
        $profile = $isDomiciliataire ? $user->profile : null;
        $rep = $isDomiciliataire ? $user->representant : null;

        return response()->json([
            'success' => true,
            'data' => [
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'telephone' => $user->telephone,

                'photo_url' => $user->photo_url,
                'initials' => $user->initials,

                'nom_societe' => $profile?->nom_societe,
                'contract_title' => $profile?->contract_title,

                'rc' => $profile?->rc,
                'if_fiscal' => $profile?->if_fiscal,
                'tp' => $profile?->tp,

                'adresses' => $profile?->adresses_list ?? [],
                'representant' => $rep,

                // A Super Admin has no company profile to complete — always
                // report true so no incomplete-profile banner ever shows for
                // them. (Frontend already guards this via auth.isAdmin in
                // showIncompleteDot; this keeps the API consistent with that.)
                'profile_complete' => $isDomiciliataire ? $user->hasCompleteProfile() : true,
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

        if (!in_array($user->role, ['domiciliataire', 'admin'], true)) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:20'],
            'prenom' => ['nullable', 'string', 'max:20'],
            'telephone' => ['nullable', 'string', 'max:30'],

            'nom_societe' => ['nullable', 'string', 'max:255'],
            'contract_title' => ['nullable', 'string', 'max:255'],

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

        if ($user->role === 'domiciliataire' && !empty($profileFields)) {
            DomiciliataireProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileFields
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'profile_complete' => $user->role === 'domiciliataire'
                ? $user->fresh()->hasCompleteProfile()
                : true,
        ]);
    }

    // ── uploadPhoto(), deletePhoto(), photo() — unchanged from before ──
    // (identical to your current version, no changes related to this refactor)

    public function uploadPhoto(Request $request)
    {
        $user = auth()->user();

        // Photo upload is available to any account that has an avatar shown
        // in the UI — domiciliataire, client, and admin all do (see
        // UserAvatar.vue, used identically in the sidebar/topbar for every
        // role).
        if (!in_array($user->role, ['domiciliataire', 'client', 'admin'], true)) {
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

        if (!in_array($user->role, ['domiciliataire', 'client', 'admin'], true)) {
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
