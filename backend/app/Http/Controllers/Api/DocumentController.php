<?php
// app/Http/Controllers/Api/DocumentController.php
// Manages tenant-scoped document uploads, metadata, and protected streams.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Concerns\UsesApiPagination;
use App\Http\Controllers\Controller;
use App\Services\Auth\QueryTokenAuthenticator;
use App\Services\Documents\DocumentService;
use App\Services\Documents\DocumentStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use AuthorizesApiRoles;
    use UsesApiPagination;

    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentStorageService $storage,
        private readonly QueryTokenAuthenticator $queryTokens,
    ) {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $entrepriseId = $request->filled('entreprise_id') ? (int) $request->query('entreprise_id') : null;

        return response()->json($this->paginatedResponse(
            $this->documents->paginateFor($user, $this->perPage($request), $entrepriseId)
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'admin'])) {
            return $blocked;
        }

        $data = $request->validate($this->writeRules() + [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->documents->create($user, $data, $request->file('file')),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'admin'])) {
            return $blocked;
        }

        return response()->json([
            'success' => true,
            'data' => $this->documents->update($user, $id, $request->validate($this->writeRules())),
        ]);
    }

    public function destroy(int $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, ['domiciliataire', 'admin'])) {
            return $blocked;
        }

        $this->documents->delete($user, $id);

        return response()->json(['success' => true, 'message' => 'Document supprimé.']);
    }

    public function download(Request $request, int $id): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $document = $this->resolveDocumentFromQueryToken($request, $id);
        if ($document instanceof \Illuminate\Http\JsonResponse) {
            return $document;
        }

        if (!$this->storage->exists($document)) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        return $this->storage->streamDownload($document, $this->documents->downloadName($document));
    }

    public function preview(Request $request, int $id): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $document = $this->resolveDocumentFromQueryToken($request, $id);
        if ($document instanceof \Illuminate\Http\JsonResponse) {
            return $document;
        }

        if (!$this->storage->exists($document)) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        return $this->storage->streamInline($document);
    }

    private function resolveDocumentFromQueryToken(Request $request, int $id): \App\Models\Document|\Illuminate\Http\JsonResponse
    {
        $user = $this->queryTokens->userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $document = $this->documents->resolveReadableDocument($user, $id);
        if (!$document) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        return $document;
    }

    private function writeRules(): array
    {
        return [
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'date_expiration' => ['nullable', 'date'],
            'previous_version_id' => ['nullable', 'integer', 'exists:documents,id'],
        ];
    }
}
