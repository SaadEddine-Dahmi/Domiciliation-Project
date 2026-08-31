<?php
// app/Services/Profile/DomiciliataireProfileService.php
// Manages company profile data and shared profile photo operations.

namespace App\Services\Profile;

use App\Models\DomiciliataireProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DomiciliataireProfileService
{
    public function show(User $user): array
    {
        $isDomiciliataire = $user->role === 'domiciliataire';
        $user->loadMissing(['profile', 'representant']);

        return [
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'telephone' => $user->telephone,
            'photo_url' => $user->photo_url,
            'initials' => $user->initials,
            'nom_societe' => $isDomiciliataire ? $user->profile?->nom_societe : null,
            'contract_title' => $isDomiciliataire ? $user->profile?->contract_title : null,
            'rc' => $isDomiciliataire ? $user->profile?->rc : null,
            'if_fiscal' => $isDomiciliataire ? $user->profile?->if_fiscal : null,
            'tp' => $isDomiciliataire ? $user->profile?->tp : null,
            'adresses' => $isDomiciliataire ? ($user->profile?->adresses_list ?? []) : [],
            'representant' => $isDomiciliataire ? $user->representant : null,
            'profile_complete' => $isDomiciliataire ? $user->hasCompleteProfile() : true,
        ];
    }

    public function update(User $user, array $data): bool
    {
        DB::transaction(function () use ($user, $data) {
            $userFields = array_intersect_key($data, array_flip(['nom', 'prenom', 'telephone']));
            if ($userFields !== []) {
                $user->update($userFields);
            }

            $profileFields = array_diff_key($data, $userFields);
            if ($user->role === 'domiciliataire' && $profileFields !== []) {
                DomiciliataireProfile::updateOrCreate(['user_id' => $user->id], $profileFields);
            }
        });

        return $user->role === 'domiciliataire'
            ? $user->fresh(['profile', 'representant'])->hasCompleteProfile()
            : true;
    }

    public function uploadPhoto(User $user, UploadedFile $photo): array
    {
        $previousPath = $user->photo_path;
        $newPath = $photo->store("profile-photos/{$user->id}", 'public');

        DB::transaction(fn() => $user->update(['photo_path' => $newPath]));

        if ($previousPath && $previousPath !== $newPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return $this->photoPayload($user->fresh());
    }

    public function deletePhoto(User $user): array
    {
        $path = $user->photo_path;

        DB::transaction(fn() => $user->update(['photo_path' => null]));

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return $this->photoPayload($user->fresh());
    }

    public function photoResponse(int $userId): ?StreamedResponse
    {
        $user = User::find($userId);
        if (!$user || !$user->photo_path || !Storage::disk('public')->exists($user->photo_path)) {
            return null;
        }

        return Storage::disk('public')->response($user->photo_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function photoPayload(User $user): array
    {
        return [
            'photo_url' => $user->photo_url,
            'initials' => $user->initials,
        ];
    }
}
