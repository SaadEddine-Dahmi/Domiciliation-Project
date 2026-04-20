<?php
// app/Http/Controllers/Api/ArticleController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * GET /api/articles
     * Returns only articles belonging to the authenticated domiciliataire.
     * Clients and admins get an empty list — they don't manage articles.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Only domiciliataires have their own article library
        if (!in_array($user->role, ['domiciliataire', 'admin'])) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $articles = Article::forTenant($user->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $articles]);
    }

    /**
     * POST /api/articles
     * Only domiciliataires can create articles.
     */
    public function store(Request $request)
    {
        // SECURITY: role check — clients must not create articles
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = Article::create([
            'domiciliataire_id' => auth()->id(),
            'title'             => $data['title'],
            'body'              => $data['body'],
            'is_active'         => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $article], 201);
    }

    /**
     * PUT /api/articles/{id}
     * Only the owning domiciliataire can update their article.
     * SECURITY: forTenant() prevents IDOR — you cannot edit another tenant's article.
     */
    public function update(Request $request, string $id)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // IDOR fix: scope to tenant before findOrFail
        $article = Article::forTenant(auth()->id())->findOrFail($id);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string', 'max:20000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $article->update($data);

        return response()->json(['success' => true, 'data' => $article->fresh()]);
    }

    /**
     * DELETE /api/articles/{id}
     * Only the owning domiciliataire can delete their article.
     */
    public function destroy(string $id)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // IDOR fix: scope to tenant
        $article = Article::forTenant(auth()->id())->findOrFail($id);
        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }
}
