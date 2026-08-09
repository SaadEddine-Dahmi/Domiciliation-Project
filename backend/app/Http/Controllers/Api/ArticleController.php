<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    // Returns all clause templates for the authenticated tenant.
    // Clients get an empty array — they don't manage an article library.
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['domiciliataire', 'admin'])) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $articles = Article::forTenant($user->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $articles]);
    }

    // Creates a new article clause template, tenant-scoped to the
    // authenticated domiciliataire automatically.
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = Article::create([
            'domiciliataire_id' => auth()->id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $article], 201);
    }

    // Updates an article's title/body/active state. forTenant() guards
    // against editing another tenant's article (IDOR).
    public function update(Request $request, string $id)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $article = Article::forTenant(auth()->id())->findOrFail($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $article->update($data);

        return response()->json(['success' => true, 'data' => $article->fresh()]);
    }

    // Permanently deletes an article. forTenant() guards against
    // deleting another tenant's article.
    public function destroy(string $id)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $article = Article::forTenant(auth()->id())->findOrFail($id);
        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }
}