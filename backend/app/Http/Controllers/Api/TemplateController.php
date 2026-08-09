<?php
// app/Http/Controllers/Api/TemplateController.php
//
// Manages reusable contract templates: a named, ordered set of articles the
// domiciliataire can build once and load instantly into any new contract
// wizard, instead of re-picking articles and re-ordering them every time.
//
// Routes (auth:sanctum):
//   GET    /api/templates       → index
//   POST   /api/templates       → store
//   PUT    /api/templates/{id}  → update
//   DELETE /api/templates/{id}  → destroy

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SyncsArticles;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    use SyncsArticles;

    /**
     * GET /api/templates
     *
     * Returns all templates for the authenticated domiciliataire, with their
     * articles eager-loaded in the saved display order.
     */
    public function index()
    {
        $templates = Template::forTenant(auth()->id())
            ->with(['articles' => fn($q) => $q->orderBy('template_articles.ordre')])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $templates]);
    }

    /**
     * POST /api/templates
     *
     * Creates a new template and attaches the selected articles with their
     * chosen display order via syncArticlesList().
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'articles'          => ['nullable', 'array'],
            'articles.*.id'     => ['required_with:articles', 'string'],
            'articles.*.ordre'  => ['nullable', 'integer'],
        ]);

        $template = Template::create([
            'domiciliataire_id' => auth()->id(),
            'name'              => $data['name'],
            'description'       => $data['description'] ?? null,
        ]);

        $this->syncArticlesList($template, $request->input('articles', []));

        return response()->json([
            'success' => true,
            'data'    => $template->load(['articles' => fn($q) => $q->orderBy('template_articles.ordre')]),
        ], 201);
    }

    /**
     * PUT /api/templates/{id}
     *
     * Updates a template's name/description and re-syncs its article selection.
     * IDOR guard: scoped to the authenticated domiciliataire.
     */
    public function update(Request $request, int $id)
    {
        $template = Template::forTenant(auth()->id())->findOrFail($id);

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'articles'          => ['nullable', 'array'],
            'articles.*.id'     => ['required_with:articles', 'string'],
            'articles.*.ordre'  => ['nullable', 'integer'],
        ]);

        $template->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if ($request->has('articles')) {
            $this->syncArticlesList($template, $request->input('articles', []));
        }

        return response()->json([
            'success' => true,
            'data'    => $template->fresh()->load(['articles' => fn($q) => $q->orderBy('template_articles.ordre')]),
        ]);
    }

    /**
     * DELETE /api/templates/{id}
     *
     * Permanently deletes a template. Contracts already created from it are
     * unaffected — the template only pre-fills the wizard, it holds no
     * ongoing relationship to contracts.
     */
    public function destroy(int $id)
    {
        $template = Template::forTenant(auth()->id())->findOrFail($id);
        $template->delete();

        return response()->json(['success' => true, 'message' => 'Modèle supprimé.']);
    }
}
