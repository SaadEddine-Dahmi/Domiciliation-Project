<?php
// app/Http/Controllers/Api/ArticleController.php
//
// REST controller for the Article (contract clause template) resource.
//
// Routes (all behind auth:sanctum middleware in routes/api.php):
//   GET    /api/articles       → index   (list clause library)
//   POST   /api/articles       → store   (create clause)
//   PUT    /api/articles/{id}  → update  (edit clause)
//   DELETE /api/articles/{id}  → destroy (permanently delete clause)
//
// Tenant isolation:
//   All queries use Article::forTenant(auth()->id()) to scope results to the
//   authenticated domiciliataire. A user can never read or modify another
//   tenant's articles even if they guess the numeric ID.
//
// Role guard:
//   Write operations require role === 'domiciliataire'.
//   Client-role users receive an empty array from index() because they have
//   no article library of their own.
//
// Delete safety:
//   destroy() calls $article->contrats()->detach() before deletion.
//   Without this, the foreign-key constraint on contrat_articles throws a 500
//   that the frontend catch block silently swallows, giving the illusion that
//   the article was deleted when it was not.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * GET /api/articles
     *
     * Returns all articles (active and inactive) belonging to the authenticated
     * domiciliataire, ordered newest-first.
     *
     * Admin role also receives articles (for oversight); client role receives
     * an empty array because clients do not manage clause libraries.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Clients have no article library — return empty array, not 403,
        // so the wizard can call this endpoint without a role check.
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
     * domiciliataire_id is set from the authenticated user — never from the
     * request body, preventing tenant injection.
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

        // 'id' is intentionally absent — auto-increment, never mass-assigned.
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
     *
     * IDOR guard: forTenant() ensures the authenticated user can only update
     * articles they own. A 404 is returned for articles belonging to other
     * tenants (deliberately indistinguishable from "not found").
     */
    public function update(Request $request, string $id)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // findOrFail inside forTenant() scope — returns 404 for cross-tenant IDs.
        $article = Article::forTenant(auth()->id())->findOrFail($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $article->update($data);

        // fresh() re-reads from the database so the response reflects the
        // actual stored values rather than the in-memory Eloquent instance.
        return response()->json(['success' => true, 'data' => $article->fresh()]);
    }

    /**
     * DELETE /api/articles/{id}
     *
     * Permanently delete an article clause template.
     *
     * CRITICAL: detach() must run before delete().
     * contrat_articles has a RESTRICT foreign key on article_id. Calling
     * delete() without detaching first throws a database integrity exception.
     * The exception propagates as a 500 response which the frontend catch
     * block silently swallows, making the delete appear to succeed when it
     * has actually failed.
     */
    public function destroy(string $id)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $article = Article::forTenant(auth()->id())->findOrFail($id);

        // Remove pivot rows first, then the article itself.
        $article->contrats()->detach();
        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }
}