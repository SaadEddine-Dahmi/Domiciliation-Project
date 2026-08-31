<?php
// app/Http/Controllers/Api/DomiciliataireProfileController.php
// Exposes company profile and profile photo endpoints.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Controller;
use App\Services\Profile\DomiciliataireProfileService;
use Illuminate\Http\Request;

class DomiciliataireProfileController extends Controller
{
    use AuthorizesApiRoles;

    public function __construct(private readonly DomiciliataireProfileService $profiles)
    {
    }

    public function show()
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'admin'])) {
            return $blocked;
        }

        return response()->json([
            'success' => true,
            'data' => $this->profiles->show($user),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'admin'])) {
            return $blocked;
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'profile_complete' => $this->profiles->update($user, $request->validate($this->profileRules())),
        ]);
    }

    public function uploadPhoto(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'client', 'admin'])) {
            return $blocked;
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil mise à jour.',
            'data' => $this->profiles->uploadPhoto($user, $request->file('photo')),
        ]);
    }

    public function deletePhoto()
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'client', 'admin'])) {
            return $blocked;
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil supprimée.',
            'data' => $this->profiles->deletePhoto($user),
        ]);
    }

    public function photo(int $userId)
    {
        return $this->profiles->photoResponse($userId) ?? abort(404);
    }

    private function profileRules(): array
    {
        return [
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
        ];
    }
}
