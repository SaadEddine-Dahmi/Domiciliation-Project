<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * GET /api/articles
     * Returns only articles belonging to the authenticated domiciliataire.
     */
    public function index(Request $request)
    {
        $tenantId = auth()->id();

        $articles = Article::forTenant($tenantId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $articles,
        ]);
    }

    /**
     * POST /api/articles
     * Creates an article owned by the authenticated domiciliataire.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = Article::create([
            'domiciliataire_id' => auth()->id(), // always force tenant
            'title'             => $data['title'],
            'body'              => $data['body'],
            'is_active'         => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $article], 201);
    }

    /**
     * PUT /api/articles/{id}
     * Updates an article — only if it belongs to the authenticated domiciliataire.
     */
    public function update(Request $request, string $id)
    {
        // Tenant check — prevents editing another domiciliataire's articles
        $article = Article::forTenant(auth()->id())->findOrFail($id);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $article->update($data);

        return response()->json(['success' => true, 'data' => $article->fresh()]);
    }

    /**
     * DELETE /api/articles/{id}
     * Deletes an article — only if it belongs to the authenticated domiciliataire.
     */
    public function destroy(string $id)
    {
        $article = Article::forTenant(auth()->id())->findOrFail($id);
        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }
}
