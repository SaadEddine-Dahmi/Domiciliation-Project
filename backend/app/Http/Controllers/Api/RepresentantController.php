<?php
// app/Http/Controllers/Api/RepresentantController.php
// Manages tenant-scoped client company representatives.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Representants\RepresentantService;
use Illuminate\Http\Request;

class RepresentantController extends Controller
{
    public function __construct(private readonly RepresentantService $representants)
    {
    }

    public function show(int $entrepriseId)
    {
        $entreprise = $this->representants->tenantEntreprise(auth()->id(), $entrepriseId);

        return response()->json(['success' => true, 'data' => $entreprise->representant]);
    }

    public function store(Request $request, int $entrepriseId)
    {
        $entreprise = $this->representants->tenantEntreprise(auth()->id(), $entrepriseId);
        if ($entreprise->representant()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette entreprise a déjà un représentant. Utilisez PUT pour modifier.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->representants->create($entreprise, $request->validate($this->createRules())),
        ], 201);
    }

    public function update(Request $request, int $entrepriseId)
    {
        $entreprise = $this->representants->tenantEntreprise(auth()->id(), $entrepriseId);
        $data = $request->validate($this->updateRules());

        if ($this->representants->needsRequiredIdentity($entreprise, $data)) {
            return $this->missingIdentityResponse();
        }

        [$representant, $created] = $this->representants->upsert($entreprise, $data);

        return response()->json(['success' => true, 'data' => $representant], $created ? 201 : 200);
    }

    public function destroy(int $entrepriseId)
    {
        $entreprise = $this->representants->tenantEntreprise(auth()->id(), $entrepriseId);
        $this->representants->delete($entreprise);

        return response()->json(['success' => true, 'message' => 'Représentant supprimé.']);
    }

    public function history(int $entrepriseId)
    {
        $entreprise = $this->representants->tenantEntreprise(auth()->id(), $entrepriseId);
        $representant = $entreprise->representant()->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $representant->history()->with('changedBy:id,nom,prenom')->get(),
        ]);
    }

    private function createRules(): array
    {
        return $this->baseRules(['nom' => 'required', 'cin' => 'required']);
    }

    private function updateRules(): array
    {
        return $this->baseRules(['nom' => 'sometimes', 'cin' => 'sometimes']);
    }

    private function baseRules(array $required): array
    {
        return [
            'nom' => [$required['nom'], 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'cin' => [$required['cin'], 'string', 'max:50'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }

    private function missingIdentityResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Nom et CIN sont requis pour créer le représentant.',
        ], 422);
    }
}
