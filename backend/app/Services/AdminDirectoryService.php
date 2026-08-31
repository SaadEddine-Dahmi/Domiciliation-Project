<?php
// app/Services/AdminDirectoryService.php
// Formats admin-facing domiciliataire directory data.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class AdminDirectoryService
{
    public function domiciliataires(): Collection
    {
        return User::where('role', 'domiciliataire')
            ->withCount(['entreprises', 'contrats'])
            ->with(['entreprises:id,domiciliataire_id,raison_sociale,statut,ville'])
            ->latest()
            ->get(['id', 'nom', 'prenom', 'email', 'telephone', 'role', 'photo_path', 'created_at'])
            ->map(fn(User $user) => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'photo_url' => $this->photoUrl($user),
                'initials' => $user->initials,
                'entreprises_count' => $user->entreprises_count,
                'contrats_count' => $user->contrats_count,
                'entreprises' => $user->entreprises->map(fn($entreprise) => [
                    'id' => $entreprise->id,
                    'raison_sociale' => $entreprise->raison_sociale,
                    'statut' => $entreprise->statut,
                    'ville' => $entreprise->ville,
                ]),
            ]);
    }

    private function photoUrl(User $user): ?string
    {
        return $user->photo_path
            ? route('users.photo', ['id' => $user->id, 'v' => substr(sha1($user->photo_path), 0, 12)])
            : null;
    }
}
