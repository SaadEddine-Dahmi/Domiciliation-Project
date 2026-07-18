<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private function disk(): string
    {
        $disks = array_keys(config('filesystems.disks', []));
        return in_array('private', $disks, true) ? 'private' : 'local';
    }

    private function authenticateViaToken(Request $request): ?\App\Models\User
    {
        $tokenValue = $request->query('token');
        if (!$tokenValue)
            return null;

        $token = PersonalAccessToken::findToken($tokenValue);
        if (!$token)
            return null;

        if ($token->expires_at && $token->expires_at->isPast())
            return null;

        return $token->tokenable;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->role;

        if ($role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first();
            if (!$entreprise)
                return response()->json(['success' => true, 'data' => []]);

            $docs = Document::query()
                ->with(['documentType'])
                ->where('entreprise_id', $entreprise->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($d) => $this->formatDoc($d, $user));

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
                'data' => $query->get()->map(fn($d) => $this->formatDoc($d, $user, withEntreprise: true)),
            ]);
        }

        $query = Document::query()
            ->with(['entreprise:id,raison_sociale', 'documentType:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('entreprise_id')) {
            $query->where('entreprise_id', (int) $request->entreprise_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn($d) => $this->formatDoc($d, $user, withEntreprise: true)),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['domiciliataire', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration' => ['nullable', 'date'],
            'previous_version_id' => ['nullable', 'integer', 'exists:documents,id'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        if ($user->role === 'domiciliataire') {
            $entreprise = Entreprise::where('domiciliataire_id', $user->id)->findOrFail($data['entreprise_id']);
        } else {
            $entreprise = Entreprise::findOrFail($data['entreprise_id']);
        }

        $path = $request->file('file')->store('documents/' . $entreprise->id, $this->disk());

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
                $user,
                withEntreprise: true
            ),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['domiciliataire', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration' => ['nullable', 'date'],
            'previous_version_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        if ($user->role === 'domiciliataire') {
            $doc = Document::whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
                ->findOrFail($id);

            Entreprise::where('domiciliataire_id', $user->id)->findOrFail($data['entreprise_id']);
        } else {
            $doc = Document::findOrFail($id);
            Entreprise::findOrFail($data['entreprise_id']);
        }

        $doc->update($data);

        return response()->json([
            'success' => true,
            'data' => $this->formatDoc(
                $doc->fresh(['entreprise:id,raison_sociale', 'documentType']),
                $user,
                withEntreprise: true
            ),
        ]);
    }

    public function destroy(int $id)
    {
        $user = auth()->user();

        if ($user->role === 'client') {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        if ($user->role === 'domiciliataire') {
            $doc = Document::whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id))
                ->findOrFail($id);
        } else {
            $doc = Document::findOrFail($id);
        }

        if (Storage::disk($this->disk())->exists($doc->file_path)) {
            Storage::disk($this->disk())->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['success' => true, 'message' => 'Document supprimé.']);
    }

    public function download(Request $request, int $id): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $user = $this->authenticateViaToken($request);
        if (!$user)
            return response()->json(['message' => 'Non authentifié.'], 401);

        $doc = $this->resolveDocForUser($id, $user);
        if (!$doc)
            return response()->json(['message' => 'Document introuvable.'], 404);

        abort_unless(Storage::disk($this->disk())->exists($doc->file_path), 404);

        $originalName = $doc->documentType?->name
            ? $doc->documentType->name . '.' . pathinfo($doc->file_path, PATHINFO_EXTENSION)
            : basename($doc->file_path);

        $mime = $this->mimeType($doc->file_path);

        return response()->streamDownload(function () use ($doc) {
            $stream = Storage::disk($this->disk())->readStream($doc->file_path);
            fpassthru($stream);
            if (is_resource($stream))
                fclose($stream);
        }, $originalName, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, int $id): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $user = $this->authenticateViaToken($request);
        if (!$user)
            return response()->json(['message' => 'Non authentifié.'], 401);

        $doc = $this->resolveDocForUser($id, $user);
        if (!$doc)
            return response()->json(['message' => 'Document introuvable.'], 404);

        abort_unless(Storage::disk($this->disk())->exists($doc->file_path), 404);

        $mime = $this->mimeType($doc->file_path);
        $size = Storage::disk($this->disk())->size($doc->file_path);

        return response()->stream(function () use ($doc) {
            $stream = Storage::disk($this->disk())->readStream($doc->file_path);
            fpassthru($stream);
            if (is_resource($stream))
                fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Content-Disposition' => 'inline; filename="' . basename($doc->file_path) . '"',
            'Cache-Control' => 'no-store, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveDocForUser(int $id, \App\Models\User $user): ?Document
    {
        $query = Document::with(['documentType', 'entreprise:id,raison_sociale']);

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first();
            if (!$entreprise)
                return null;
            $query->where('entreprise_id', $entreprise->id);
        } elseif ($user->role === 'domiciliataire') {
            $query->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $user->id));
        }

        return $query->find($id);
    }

    private function mimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function formatDoc(Document $doc, \App\Models\User $user, bool $withEntreprise = false): array
    {
        $ext = strtolower(pathinfo($doc->file_path ?? '', PATHINFO_EXTENSION));

        $base = [
            'id' => $doc->id,
            'document_type_id' => $doc->document_type_id,
            'name' => $doc->documentType?->name ?? 'Document',
            'extension' => $ext,
            'is_pdf' => $ext === 'pdf',
            'document_type' => $doc->documentType ? [
                'id' => $doc->documentType->id,
                'name' => $doc->documentType->name,
            ] : null,
            'date_expiration' => $doc->date_expiration?->format('Y-m-d'),
            'created_at' => $doc->created_at,
            'download_url' => url("/api/documents/{$doc->id}/download"),
            'preview_url' => url("/api/documents/{$doc->id}/preview"),
            'file_path' => null,
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