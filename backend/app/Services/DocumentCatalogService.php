<?php
// app/Services/DocumentCatalogService.php
// Manages the shared document type catalog.

namespace App\Services;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DocumentCatalogService
{
    public function all(): Collection
    {
        return DocumentType::orderBy('name')->get();
    }

    public function create(array $data): DocumentType
    {
        return DB::transaction(fn() => DocumentType::create($data));
    }
}
