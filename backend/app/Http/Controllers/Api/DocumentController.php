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
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->role;

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
                    'id'              => $d->id,
                    'name'            => $d->documentType?->name ?? 'Document',
                    // SECURITY: never expose raw file_path or public storage URL
                    // Use authenticated download endpoint instead
                    'download_url'    => route('documents.download', $d->id),
                    'date_expiration' => $d->date_expiration,
                    'created_at'      => $d->created_at,
                ]);

            return response()->json(['success' => true, 'data' => $docs]);
        }

        if ($role === 'domiciliataire') {
            $query = Document::query()
                ->with(['entreprise:id,raison_sociale', 'documentType'])
                ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
                ->orderByDesc('created_at');

            if ($request->filled('entreprise_id')) {
                $query->where('entreprise_id', (int) $request->entreprise_id);
            }

            return response()->json([
                'success' => true,
                'data'    => $query->get()->map(fn($d) => [
                    ...$d->toArray(),
                    // Authenticated download URL instead of public storage path
                    'download_url' => route('documents.download', $d->id),
                    // Never expose raw file_path to frontend
                    'file_path'    => null,
                ]),
            ]);
        }

        // Admin: metadata only, no file access
        $query = Document::query()
            ->with(['entreprise:id,raison_sociale', 'documentType:id,name'])
            ->select('id', 'entreprise_id', 'document_type_id', 'date_expiration', 'created_at')
            ->orderByDesc('created_at');

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['domiciliataire', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'entreprise_id'      => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id'   => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration'    => ['nullable', 'date'],
            'previous_version_id'=> ['nullable', 'integer', 'exists:documents,id'],
            'file'               => ['required', 'file', 'max:10240',
                                     // SECURITY: restrict allowed MIME types
                                     'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $entreprise = Entreprise::where('domiciliataire_id', $user->id)
            ->findOrFail($data['entreprise_id']);

        // SECURITY: store in private disk (not public)
        // Files are NOT accessible via direct URL — only via download endpoint
        $path = $request->file('file')->store(
            'documents/' . $entreprise->id,
            'private'  // ← private disk, not public
        );

        $doc = Document::create([
            'entreprise_id'       => $entreprise->id,
            'document_type_id'    => $data['document_type_id'],
            'file_path'           => $path,
            'date_expiration'     => $data['date_expiration'] ?? null,
            'uploaded_by_user'    => $user->id,
            'previous_version_id' => $data['previous_version_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                ...$doc->load(['entreprise:id,raison_sociale', 'documentType'])->toArray(),
                'download_url' => route('documents.download', $doc->id),
                'file_path'    => null,
            ],
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = auth()->user();

        $doc = Document::whereHas(
            'entreprise',
            fn($q) => $q->where('domiciliataire_id', $user->id)
        )->findOrFail($id);

        $data = $request->validate([
            'entreprise_id'      => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id'   => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration'    => ['nullable', 'date'],
            'previous_version_id'=> ['nullable', 'integer', 'exists:documents,id'],
        ]);

        Entreprise::where('domiciliataire_id', $user->id)->findOrFail($data['entreprise_id']);
        $doc->update($data);

        return response()->json([
            'success' => true,
            'data'    => [
                ...$doc->fresh(['entreprise:id,raison_sociale', 'documentType'])->toArray(),
                'download_url' => route('documents.download', $doc->id),
                'file_path'    => null,
            ],
        ]);
    }

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

        // Delete from private storage
        if (Storage::disk('private')->exists($doc->file_path)) {
            Storage::disk('private')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['success' => true, 'message' => 'Document supprimé.']);
    }

    /**
     * GET /api/documents/{id}/download
     * Authenticated, authorized file download.
     * Checks ownership before streaming — file never directly accessible.
     */
    public function download(int $id): StreamedResponse
    {
        $user = auth()->user();

        // Build query based on role
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
        // Admin can access any document

        $doc = $query->findOrFail($id);

        // Verify file exists on private disk
        abort_unless(Storage::disk('private')->exists($doc->file_path), 404);

        return Storage::disk('private')->download(
            $doc->file_path,
            basename($doc->file_path)
        );
    }
}
