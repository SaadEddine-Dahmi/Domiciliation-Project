<?php
// app/Http/Controllers/Api/DomiciliataireRepresentantController.php
// Manages the authenticated tenant owner's legal representative.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Controller;
use App\Services\Representants\RepresentantService;
use Illuminate\Http\Request;

class DomiciliataireRepresentantController extends Controller
{
    use AuthorizesApiRoles;

    public function __construct(private readonly RepresentantService $representants)
    {
    }

    public function show()
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire')) {
            return $blocked;
        }

        return response()->json(['success' => true, 'data' => $user->representant]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire')) {
            return $blocked;
        }

        $data = $request->validate($this->rules());
        if ($this->representants->needsRequiredIdentity($user, $data)) {
            return response()->json([
                'success' => false,
                'message' => 'Nom et CIN sont requis pour créer le représentant.',
            ], 422);
        }

        [$representant, $created] = $this->representants->upsert($user, $data);

        return response()->json(['success' => true, 'data' => $representant], $created ? 201 : 200);
    }

    private function rules(): array
    {
        return [
            'nom' => ['sometimes', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'cin' => ['sometimes', 'string', 'max:50'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }
}
