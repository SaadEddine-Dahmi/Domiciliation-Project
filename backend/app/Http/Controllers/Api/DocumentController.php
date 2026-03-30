<?php
// ============================================================
// app/Http/Controllers/Api/DocumentController.php
// Permissions selon le rôle :
//   domiciliataire → voit/gère tous les docs de ses entreprises
//   client         → voit uniquement les docs de son entreprise
//   admin          → lecture seule (pas d'accès fichiers sensibles)
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->role;

        // ── Client : uniquement ses propres documents ─────
        if ($role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first();
            if (!$entreprise) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $docs = Document::query()
                ->with(['documentType'])
                ->where('entreprise_id', $entreprise->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->documentType?->name ?? 'Document',
                    'file_path' => $d->file_path,
                    'url' => asset('storage/' . $d->file_path),
                    'date_expiration' => $d->date_expiration,
                    'created_at' => $d->created_at,
                ]);

            return response()->json(['success' => true, 'data' => $docs]);
        }

        // ── Domiciliataire : docs de ses entreprises ──────
        if ($role === 'domiciliataire') {
            $query = Document::query()
                ->with(['entreprise:id,raison_sociale', 'documentType'])
                ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
                ->orderByDesc('created_at');

            if ($request->filled('entreprise_id')) {
                $query->where('entreprise_id', (int) $request->entreprise_id);
            }

            return response()->json(['success' => true, 'data' => $query->get()]);
        }

        // ── Admin : liste des entreprises sans fichiers sensibles
        // L'admin ne voit PAS les fichiers, seulement les métadonnées
        $query = Document::query()
            ->with(['entreprise:id,raison_sociale', 'documentType:id,name'])
            ->select('id', 'entreprise_id', 'document_type_id', 'date_expiration', 'created_at')
            ->orderByDesc('created_at');

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Seul le domiciliataire peut uploader
        if (!in_array($user->role, ['domiciliataire', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration' => ['nullable', 'date'],
            'previous_version_id' => ['nullable', 'integer', 'exists:documents,id'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        // Vérifier que l'entreprise appartient au domiciliataire
        $entreprise = Entreprise::where('domiciliataire_id', $user->id)->findOrFail($data['entreprise_id']);

        $path = $request->file('file')->store('documents', 'public');

        $doc = Document::create([
            'entreprise_id' => $entreprise->id,
            'document_type_id' => $data['document_type_id'],
            'file_path' => $path,
            'date_expiration' => $data['date_expiration'] ?? null,
            'uploaded_by_user' => $user->id,
            'previous_version_id' => $data['previous_version_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $doc->load(['entreprise:id,raison_sociale', 'documentType']),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = auth()->user();

        $doc = Document::whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
            ->findOrFail($id);

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration' => ['nullable', 'date'],
            'previous_version_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        Entreprise::where('domiciliataire_id', $user->id)->findOrFail($data['entreprise_id']);
        $doc->update($data);

        return response()->json([
            'success' => true,
            'data' => $doc->fresh(['entreprise:id,raison_sociale', 'documentType']),
        ]);
    }

    public function destroy(int $id)
    {
        $user = auth()->user();

        // Client ne peut pas supprimer
        if ($user->role === 'client') {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $doc = Document::whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
            ->findOrFail($id);

        if (Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['success' => true, 'message' => 'Document supprimé.']);
    }
}