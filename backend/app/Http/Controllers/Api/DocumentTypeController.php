<?php
// app/Http/Controllers/Api/DocumentTypeController.php
// Exposes the shared document type catalog.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentCatalogService;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function __construct(private readonly DocumentCatalogService $catalog)
    {
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => $this->catalog->all(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->catalog->create($request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:document_types,name'],
            'is_required' => ['boolean'],
            'has_expiration' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['success' => true, 'data' => $type], 201);
    }
}
