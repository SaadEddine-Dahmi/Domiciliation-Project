<?php
// app/Http/Controllers/Api/TemplateController.php
// Manages tenant-scoped reusable contract templates.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SyncsArticles;
use App\Http\Controllers\Concerns\UsesApiPagination;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    use SyncsArticles;
    use UsesApiPagination;

    public function index(Request $request)
    {
        $templates = Template::forTenant(auth()->id())
            ->with($this->orderedArticles())
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($this->paginatedResponse($templates));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $template = DB::transaction(function () use ($data, $request) {
            $template = Template::create([
                'domiciliataire_id' => auth()->id(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $this->syncArticlesList($template, $request->input('articles', []));

            return $template;
        });

        return response()->json([
            'success' => true,
            'data' => $template->load($this->orderedArticles()),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $template = Template::forTenant(auth()->id())->findOrFail($id);
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($template, $data, $request) {
            $template->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if ($request->has('articles')) {
                $this->syncArticlesList($template, $request->input('articles', []));
            }
        });

        return response()->json([
            'success' => true,
            'data' => $template->fresh()->load($this->orderedArticles()),
        ]);
    }

    public function destroy(int $id)
    {
        $template = Template::forTenant(auth()->id())->findOrFail($id);
        DB::transaction(fn() => $template->delete());

        return response()->json(['success' => true, 'message' => 'Modèle supprimé.']);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'articles' => ['nullable', 'array'],
            'articles.*.id' => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ];
    }

    private function orderedArticles(): array
    {
        return ['articles' => fn($query) => $query->orderBy('template_articles.ordre')];
    }
}
