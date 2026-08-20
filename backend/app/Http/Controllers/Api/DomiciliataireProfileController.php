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
     * Returns the authenticated domiciliataire's company profile. The
     * profile relation may be null if it was never saved — every field
     * falls back to null/empty in that case rather than erroring.
     */
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

                // photo_url/initials come from the User model's $appends —
                // photo_url is null until a photo is uploaded, in which
                // case the frontend should show initials instead.
                'photo_url' => $user->photo_url,
                'initials' => $user->initials,

                'nom_societe' => $profile?->nom_societe,

                // Heading printed at the top of the generated contract.
                // Nullable — the contract generator falls back to a
                // default title when this is empty.
                'contract_title' => $profile?->contract_title,

                'representant_legal' => $profile?->representant_legal,
                'identite_representant' => $profile?->identite_representant,

                // Contact details of the domiciliataire's legal representative —
                // shown on the contract's "D'une part" block when filled.
                'representant_email' => $profile?->representant_email,
                'representant_telephone' => $profile?->representant_telephone,

                'rc' => $profile?->rc,
                'if_fiscal' => $profile?->if_fiscal,
                'tp' => $profile?->tp,

                // Array of {label, value}. First entry is treated as the siège
                // social; every entry after it as a succursale — see
                // ContratController::buildTokenMap() for how these are
                // formatted onto the contract PDF.
                'adresses' => $profile?->adresses_list ?? [],

                'profile_complete' => $user->hasCompleteProfile(),
            ],
        ]);
    }

    /**
     * PUT /api/profile
     *
     * Saves the company profile. Contact fields (nom/prenom/telephone)
     * update the users row; everything else — including the new
     * contract_title — is upserted into domiciliataire_profiles, created
     * on first save. Photo uploads are handled separately by
     * uploadPhoto()/deletePhoto() below, never through this endpoint.
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

            // Free text chosen by the domiciliataire — this is what lets
            // each client pick the title that appears on their own
            // generated contract instead of a hardcoded string.
            'contract_title' => ['nullable', 'string', 'max:255'],

            'representant_legal' => ['nullable', 'string', 'max:255'],
            'identite_representant' => ['nullable', 'string', 'max:100'],

            // Nullable — the domiciliataire can save a partial profile and
            // complete contact info later. Neither is required to reach
            // hasCompleteProfile() (see User::hasCompleteProfile()).
            'representant_email' => ['nullable', 'email', 'max:150'],
            'representant_telephone' => ['nullable', 'string', 'max:50'],

            'rc' => ['nullable', 'string', 'max:100'],
            'if_fiscal' => ['nullable', 'string', 'max:100'],
            'tp' => ['nullable', 'string', 'max:100'],

            // First address in the array = siège social.
            // Every subsequent address = a succursale.
            // Order is preserved as submitted — the frontend controls it.
            'adresses' => ['nullable', 'array'],
            'adresses.*.label' => ['required_with:adresses', 'string', 'max:100'],
            'adresses.*.value' => ['required_with:adresses', 'string', 'max:500'],
        ]);

        $userFields = array_intersect_key($data, array_flip(['nom', 'prenom', 'telephone']));
        if (!empty($userFields)) {
            $user->update($userFields);
        }

        $profileFields = array_diff_key($data, $userFields);

        DomiciliataireProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileFields
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'profile_complete' => $user->fresh()->hasCompleteProfile(),
        ]);
    }

    /**
     * POST /api/profile/photo
     *
     * Uploads (or replaces) the authenticated domiciliataire's profile
     * photo. The previous file, if any, is deleted only AFTER the new
     * one is stored successfully — so a failed upload never leaves the
     * user with no photo at all.
     */
    public function uploadPhoto(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'], // 2 MB max
        ]);

        $previousPath = $user->photo_path;

        // Stored under profile-photos/{user_id}/ so files never collide
        // between accounts and can be wiped by deleting the folder.
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

    /**
     * DELETE /api/profile/photo
     *
     * Removes the current profile photo from disk and clears the column.
     * The UI then falls back automatically to nom/prenom initials
     * (see User::getInitialsAttribute()).
     */
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

    /**
     * GET /api/users/{id}/photo
     *
     * Publicly streams a user's profile photo straight from the "public"
     * disk. Public (no auth:sanctum) for the same reason as
     * documents/download and contrats/pdf/stream in routes/api.php: an
     * <img src="..."> tag cannot attach an Authorization header, so any
     * URL an <img> points to must work without one.
     *
     * FIX: this replaces the previous approach of pointing photo_url
     * directly at the "public" disk's symlinked /storage/... path. That
     * approach silently breaks whenever `php artisan storage:link` hasn't
     * been run, or when APP_URL doesn't match a host the browser can
     * actually reach (very common in Docker, where APP_URL is often an
     * internal-only service name). Streaming through this route removes
     * both dependencies — see User::getPhotoUrlAttribute() for how the
     * URL is built.
     *
     * A profile photo is treated as a non-sensitive avatar image (same
     * trust level as the other public routes above), so no per-request
     * token or tenant check is needed — worst case a stranger who guesses
     * a user ID sees their profile picture, nothing more.
     *
     * Returns a clean 404 (never a broken/empty 200) whenever there's no
     * photo, so the frontend's <img @error> fallback to initials fires
     * immediately instead of showing a broken image icon.
     */
    public function photo(int $userId)
    {
        $user = User::find($userId);

        if (!$user || !$user->photo_path || !Storage::disk('public')->exists($user->photo_path)) {
            abort(404);
        }

        // Storage::response() sets the correct Content-Type from the file
        // itself, so JPG/PNG/WEBP all serve correctly without guessing.
        return Storage::disk('public')->response($user->photo_path, null, [
            // Avatars rarely change — let browsers cache them a day
            // instead of re-fetching on every page load.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}