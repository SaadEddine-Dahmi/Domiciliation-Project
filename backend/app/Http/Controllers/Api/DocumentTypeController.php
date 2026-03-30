<?php
// ============================================================
// app/Http/Controllers/Api/DocumentTypeController.php
// CRUD types de documents — domiciliataire peut créer ses propres types
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => DocumentType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100', 'unique:document_types,name'],
            'is_required'    => ['boolean'],
            'has_expiration' => ['boolean'],
            'description'    => ['nullable', 'string', 'max:500'],
        ]);

        $type = DocumentType::create($data);

        return response()->json(['success' => true, 'data' => $type], 201);
    }
}
