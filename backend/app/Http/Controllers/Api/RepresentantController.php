<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Representant;
use App\Models\Entreprise;
use Illuminate\Http\Request;

class RepresentantController extends Controller
{
    // Liste les représentants d'une entreprise
    public function index(int $entrepriseId)
    {
        $tenantId = auth()->id();
        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail($entrepriseId);

        return response()->json([
            'success' => true,
            'data' => Representant::where('entreprise_id', $entrepriseId)->get(),
        ]);
    }

    // Créer un représentant lié à une entreprise
    public function store(Request $request, int $entrepriseId)
    {
        $tenantId = auth()->id();
        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail($entrepriseId);

        $data = $request->validate([
            'nom'            => ['required', 'string', 'max:100'],
            'prenom'         => ['nullable', 'string', 'max:100'],
            'cin'            => ['required', 'string', 'max:50'],
            'nationalite'    => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse'        => ['nullable', 'string'],
            'telephone'      => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
        ]);

        $rep = Representant::create([...$data, 'entreprise_id' => $entrepriseId]);

        return response()->json(['success' => true, 'data' => $rep], 201);
    }

    // Modifier un représentant
    public function update(Request $request, int $entrepriseId, int $id)
    {
        $tenantId = auth()->id();
        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail($entrepriseId);

        $rep = Representant::where('entreprise_id', $entrepriseId)->findOrFail($id);

        $data = $request->validate([
            'nom'            => ['required', 'string', 'max:100'],
            'prenom'         => ['nullable', 'string', 'max:100'],
            'cin'            => ['required', 'string', 'max:50'],
            'nationalite'    => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date'],
            'adresse'        => ['nullable', 'string'],
            'telephone'      => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
        ]);

        $rep->update($data);

        return response()->json(['success' => true, 'data' => $rep->fresh()]);
    }

    // Supprimer un représentant
    public function destroy(int $entrepriseId, int $id)
    {
        $tenantId = auth()->id();
        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail($entrepriseId);

        Representant::where('entreprise_id', $entrepriseId)->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Représentant supprimé.']);
    }
}
