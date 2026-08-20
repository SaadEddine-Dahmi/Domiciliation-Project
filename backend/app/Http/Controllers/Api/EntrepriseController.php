<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntrepriseStoreRequest;
use App\Http\Requests\EntrepriseUpdateRequest;
use App\Http\Resources\EntrepriseResource;
use App\Models\Entreprise;
use Illuminate\Http\Request;

class EntrepriseController extends Controller
{
    // Lists tenant-scoped entreprises with optional search across
    // raison_sociale, ville, and statut, paginated (max 100/page).
    public function index(Request $request)
    {
        $tenantId = auth()->id();

        $query = Entreprise::forTenant($tenantId);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('raison_sociale', 'ilike', "%{$search}%")
                    ->orWhere('ville', 'ilike', "%{$search}%")
                    ->orWhere('statut', 'ilike', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $rows = $query->latest()->paginate($perPage);

        return EntrepriseResource::collection($rows);
    }

    // Creates a new entreprise, forcing domiciliataire_id to the
    // authenticated tenant. Domiciliataire-only.
    public function store(EntrepriseStoreRequest $request)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validated();

        $row = Entreprise::create([
            ...$data,
            'domiciliataire_id' => auth()->id(),
        ]);

        return (new EntrepriseResource($row))
            ->response()
            ->setStatusCode(201);
    }

    // Returns a single entreprise, tenant-scoped.
    public function show(int $id)
    {
        $row = Entreprise::forTenant(auth()->id())->findOrFail($id);
        return new EntrepriseResource($row);
    }

    // Updates an entreprise, tenant-scoped.
    public function update(EntrepriseUpdateRequest $request, int $id)
    {
        $row = Entreprise::forTenant(auth()->id())->findOrFail($id);
        $row->update($request->validated());

        return new EntrepriseResource($row->fresh());
    }

    // Deletes an entreprise, tenant-scoped.
    public function destroy(int $id)
    {
        $row = Entreprise::forTenant(auth()->id())->findOrFail($id);
        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Entreprise supprimée.',
        ]);
    }
}