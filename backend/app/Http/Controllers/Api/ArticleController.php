<?php
// app/Http/Controllers/Api/ArticleController.php
// Manages tenant-scoped reusable contract article clauses.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Concerns\UsesApiPagination;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleLibraryService;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    use AuthorizesApiRoles;
    use UsesApiPagination;

    public function __construct(private readonly ArticleLibraryService $articles)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['domiciliataire', 'admin'], true)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $articles = Article::forTenant($user->id)
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($this->paginatedResponse($articles));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire')) {
            return $blocked;
        }

        $article = $this->articles->create($user, $this->validatedPayload($request));

        return response()->json(['success' => true, 'data' => $article], 201);
    }

    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire')) {
            return $blocked;
        }

        $article = Article::forTenant($user->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->articles->update($article, $this->validatedPayload($request, requireActive: true)),
        ]);
    }

    public function destroy(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire')) {
            return $blocked;
        }

        $article = Article::forTenant($user->id)->findOrFail($id);
        if (!$this->articles->delete($article)) {
            return response()->json([
                'success' => false,
                'message' => 'Cet article est utilise dans un contrat ou un modele et ne peut pas etre supprime.',
            ], 409);
        }

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }

    private function validatedPayload(Request $request, bool $requireActive = false): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => [$requireActive ? 'required' : 'nullable', 'boolean'],
        ]);
    }
}
