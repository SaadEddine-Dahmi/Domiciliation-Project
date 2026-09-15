<?php
// app/Services/Profile/DomiciliataireProfileService.php
// Manages company profile data and shared profile photo operations.

namespace App\Services\Profile;

use App\Models\DomiciliataireProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $newPath = $this->storePhoto($user, $photo);

        DB::transaction(fn() => $user->update(['photo_path' => $newPath]));

        if ($previousPath && $previousPath !== $newPath) {
            Storage::disk($this->disk())->delete($previousPath);
        }

        return $this->photoPayload($user->fresh());
    }

    public function deletePhoto(User $user): array
    {
        $path = $user->photo_path;

        DB::transaction(fn() => $user->update(['photo_path' => null]));

        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }

        return $this->photoPayload($user->fresh());
    }

    public function photoResponse(int $userId): ?StreamedResponse
    {
        $user = User::find($userId);
        if (!$user || !$user->photo_path || !Storage::disk($this->disk())->exists($user->photo_path)) {
            return null;
        }

        return Storage::disk($this->disk())->response($user->photo_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function disk(): string
    {
        $disk = config('filesystems.tenant_assets_disk');

        if (!is_string($disk) || $disk === '') {
            throw new \RuntimeException('Tenant asset storage disk is not configured.');
        }

        return $disk;
    }

    private function storePhoto(User $user, UploadedFile $photo): string
    {
        $extension = $photo->extension() ?: $photo->guessExtension() ?: 'bin';
        $path = "tenants/{$user->id}/assets/profile-photos/" . Str::uuid() . ".{$extension}";

        try {
            $stored = Storage::disk($this->disk())->putFileAs(
                "tenants/{$user->id}/assets/profile-photos",
                $photo,
                basename($path)
            );
        } catch (\Throwable $e) {
            Log::error('Profile photo upload failed', [
                'user_id' => $user->id,
                'disk' => $this->disk(),
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to store the profile photo.', previous: $e);
        }

        if (!$stored) {
            throw new \RuntimeException('Unable to store the profile photo.');
        }

        return $stored;
    }

    private function photoPayload(User $user): array
    {
        return [
            'photo_url' => $user->photo_url,
            'initials' => $user->initials,
        ];
    }
}
