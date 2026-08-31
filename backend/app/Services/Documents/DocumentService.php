<?php
// app/Services/Documents/DocumentService.php
// Handles tenant-scoped document queries, writes, and response formatting.

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    public function __construct(private readonly DocumentStorageService $storage)
    {
    }

    public function paginateFor(User $user, int $perPage, ?int $entrepriseId = null): LengthAwarePaginator
    {
        $query = $this->queryFor($user)->orderByDesc('created_at');

        if ($entrepriseId) {
            $query->where('entreprise_id', $entrepriseId);
        }

        return $query->paginate($perPage)
            ->through(fn(Document $document) => $this->format($document, $user, $user->role !== 'client'));
    }

    public function create(User $user, array $data, UploadedFile $file): array
    {
        return DB::transaction(function () use ($user, $data, $file) {
            $entreprise = $this->resolveWritableEntreprise($user, (int) $data['entreprise_id']);
            $path = $file->store('documents/' . $entreprise->id, $this->storage->disk());

            $document = Document::create([
                'entreprise_id' => $entreprise->id,
                'document_type_id' => $data['document_type_id'],
                'file_path' => $path,
                'date_expiration' => $data['date_expiration'] ?? null,
                'uploaded_by_user' => $user->id,
                'previous_version_id' => $data['previous_version_id'] ?? null,
            ]);

            return $this->format($document->load($this->formatRelations()), $user, withEntreprise: true);
        });
    }

    public function update(User $user, int $id, array $data): array
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $document = $this->resolveWritableDocument($user, $id);
            $this->resolveWritableEntreprise($user, (int) $data['entreprise_id']);

            $document->update($data);

            return $this->format($document->fresh($this->formatRelations()), $user, withEntreprise: true);
        });
    }

    public function delete(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $document = $this->resolveWritableDocument($user, $id);
            $this->storage->delete($document);
            $document->delete();
        });
    }

    public function resolveReadableDocument(User $user, int $id): ?Document
    {
        return $this->queryFor($user)
            ->with($this->formatRelations())
            ->find($id);
    }

    public function downloadName(Document $document): string
    {
        return $document->documentType?->name
            ? $document->documentType->name . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION)
            : basename($document->file_path);
    }

    public function format(Document $document, User $user, bool $withEntreprise = false): array
    {
        $extension = strtolower(pathinfo($document->file_path ?? '', PATHINFO_EXTENSION));

        $payload = [
            'id' => $document->id,
            'document_type_id' => $document->document_type_id,
            'name' => $document->documentType?->name ?? 'Document',
            'extension' => $extension,
            'is_pdf' => $extension === 'pdf',
            'document_type' => $document->documentType ? [
                'id' => $document->documentType->id,
                'name' => $document->documentType->name,
            ] : null,
            'date_expiration' => $document->date_expiration?->format('Y-m-d'),
            'created_at' => $document->created_at,
            'download_url' => url("/api/documents/{$document->id}/download"),
            'preview_url' => url("/api/documents/{$document->id}/preview"),
            'file_path' => null,
        ];

        if ($withEntreprise && $document->relationLoaded('entreprise')) {
            $payload['entreprise_id'] = $document->entreprise_id;
            $payload['entreprise'] = $document->entreprise ? [
                'id' => $document->entreprise->id,
                'raison_sociale' => $document->entreprise->raison_sociale,
            ] : null;
        }

        return $payload;
    }

    private function queryFor(User $user): Builder
    {
        $query = Document::query()->with($this->formatRelations());

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first(['id']);

            return $entreprise ? $query->where('entreprise_id', $entreprise->id) : $query->whereRaw('1 = 0');
        }

        if ($user->role === 'domiciliataire') {
            return $query->whereHas('entreprise', fn($tenant) => $tenant->where('domiciliataire_id', $user->id));
        }

        return $query;
    }

    private function resolveWritableDocument(User $user, int $id): Document
    {
        return $user->role === 'domiciliataire'
            ? Document::whereHas('entreprise', fn($query) => $query->where('domiciliataire_id', $user->id))->findOrFail($id)
            : Document::findOrFail($id);
    }

    private function resolveWritableEntreprise(User $user, int $id): Entreprise
    {
        return $user->role === 'domiciliataire'
            ? Entreprise::where('domiciliataire_id', $user->id)->findOrFail($id)
            : Entreprise::findOrFail($id);
    }

    private function formatRelations(): array
    {
        return ['entreprise:id,raison_sociale', 'documentType:id,name'];
    }
}
