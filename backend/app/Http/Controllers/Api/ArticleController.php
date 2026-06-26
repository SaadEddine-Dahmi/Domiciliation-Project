<?php
// app/Http/Controllers/Api/ArticleController.php
//
// REST controller for the Article resource.
//
// Articles are reusable contract clause templates owned by a domiciliataire.
// Their bodies may contain {{variable}} placeholder tokens resolved at PDF
// generation time.
//
// Routes (all behind auth:sanctum):
//   GET    /api/articles       → index
//   POST   /api/articles       → store
//   PUT    /api/articles/{id}  → update
//   DELETE /api/articles/{id}  → destroy
//
// Security:
//   - Write operations (store, update, destroy) require role === 'domiciliataire'.
//   - All queries use Article::forTenant() to prevent IDOR attacks.
//   - Client-role users receive an empty array from index() — they do not
//     manage article templates.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * GET /api/articles
     *
     * Returns all active and inactive articles belonging to the authenticated
     * domiciliataire, ordered newest-first.
     *
     * Client users receive an empty array — they have no article library.
     */
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

    /**
     * POST /api/articles
     *
     * Create a new article clause template.
     * The article is automatically linked to the authenticated user's tenant ID.
     */
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

    /**
     * PUT /api/articles/{id}
     *
     * Update an article's title, body, or active state.
     * IDOR guard: forTenant() ensures the authenticated user can only
     * update their own articles, not another tenant's.
     */
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

    /**
     * DELETE /api/articles/{id}
     *
     * Permanently delete an article clause template.
     * IDOR guard: forTenant() ensures you can only delete your own articles.
     */
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