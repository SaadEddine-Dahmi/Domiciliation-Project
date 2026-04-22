<?php
// app/Http/Controllers/Api/DocumentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    // ── Disk helper ────────────────────────────────────────
    // Uses 'private' disk if configured, falls back to 'local'.
    // This prevents 500 errors when filesystems.php doesn't define 'private'.
    private function disk(): string
    {
        $disks = array_keys(config('filesystems.disks', []));
        return in_array('private', $disks) ? 'private' : 'local';
    }

    // ── Index ──────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->role;

        // ── Client: own documents ──────────────────────────
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
                ->map(fn($d) => $this->formatDoc($d));

            return response()->json(['success' => true, 'data' => $docs]);
        }

        // ── Domiciliataire: all their clients' documents ───
        if ($role === 'domiciliataire') {
            $query = Document::query()
                ->with(['entreprise:id,raison_sociale', 'documentType'])
                ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
                ->orderByDesc('created_at');

            // Optional filter by client
            if ($request->filled('entreprise_id')) {
                $query->where('entreprise_id', (int) $request->entreprise_id);
            }

            return response()->json([
                'success' => true,
                'data' => $query->get()->map(fn($d) => $this->formatDoc($d, withEntreprise: true)),
            ]);
        }

        // ── Admin: metadata only ───────────────────────────
        $query = Document::query()
            ->with(['entreprise:id,raison_sociale', 'documentType:id,name'])
            ->select('id', 'entreprise_id', 'document_type_id', 'date_expiration', 'created_at')
            ->orderByDesc('created_at');

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // ── Store ──────────────────────────────────────────────
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['domiciliataire', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration' => ['nullable', 'date'],
            'previous_version_id' => ['nullable', 'integer', 'exists:documents,id'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        // Verify the entreprise belongs to this domiciliataire
        $entreprise = Entreprise::where('domiciliataire_id', $user->id)
            ->findOrFail($data['entreprise_id']);

        // Store file on configured disk
        $path = $request->file('file')->store(
            'documents/' . $entreprise->id,
            $this->disk()
        );

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
            'data' => $this->formatDoc(
                $doc->load(['entreprise:id,raison_sociale', 'documentType']),
                withEntreprise: true
            ),
        ], 201);
    }

    // ── Update ─────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $user = auth()->user();

        $doc = Document::whereHas(
            'entreprise',
            fn($q) => $q->where('domiciliataire_id', $user->id)
        )->findOrFail($id);

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
            'data' => $this->formatDoc(
                $doc->fresh(['entreprise:id,raison_sociale', 'documentType']),
                withEntreprise: true
            ),
        ]);
    }

    // ── Destroy ────────────────────────────────────────────
    public function destroy(int $id)
    {
        $user = auth()->user();

        if ($user->role === 'client') {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $doc = Document::whereHas(
            'entreprise',
            fn($q) => $q->where('domiciliataire_id', $user->id)
        )->findOrFail($id);

        if (Storage::disk($this->disk())->exists($doc->file_path)) {
            Storage::disk($this->disk())->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['success' => true, 'message' => 'Document supprimé.']);
    }

    // ── Download ───────────────────────────────────────────
    // Authenticated, role-checked file streaming.
    // File never directly accessible via URL.
    public function download(int $id): StreamedResponse
    {
        $user = auth()->user();
        $query = Document::query();

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->firstOrFail();
            $query->where('entreprise_id', $entreprise->id);
        } elseif ($user->role === 'domiciliataire') {
            $query->whereHas(
                'entreprise',
                fn($q) => $q->where('domiciliataire_id', $user->id)
            );
        }
        // admin: no filter — can access all

        $doc = $query->findOrFail($id);

        abort_unless(Storage::disk($this->disk())->exists($doc->file_path), 404);

        return Storage::disk($this->disk())->download(
            $doc->file_path,
            basename($doc->file_path)
        );
    }

    // ── Format helper ──────────────────────────────────────
    private function formatDoc(Document $doc, bool $withEntreprise = false): array
    {
        $base = [
            'id' => $doc->id,
            'document_type_id' => $doc->document_type_id,
            'name' => $doc->documentType?->name ?? 'Document',
            'document_type' => $doc->documentType ? [
                'id' => $doc->documentType->id,
                'name' => $doc->documentType->name,
            ] : null,
            'date_expiration' => $doc->date_expiration?->format('Y-m-d'),
            'created_at' => $doc->created_at,
            'download_url' => route('documents.download', $doc->id),
            'file_path' => null, // never expose raw path
        ];

        if ($withEntreprise && $doc->relationLoaded('entreprise')) {
            $base['entreprise_id'] = $doc->entreprise_id;
            $base['entreprise'] = $doc->entreprise ? [
                'id' => $doc->entreprise->id,
                'raison_sociale' => $doc->entreprise->raison_sociale,
            ] : null;
        }

        return $base;
    }
}