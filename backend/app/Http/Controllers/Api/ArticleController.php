<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Article::where('is_active', true)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = Article::create([
            'title'     => $data['title'],
            'body'      => $data['body'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $article], 201);
    }

    public function update(Request $request, string $id)   // ← string, pas int (UUID)
    {
        $article = Article::findOrFail($id);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $article->update($data);

        return response()->json(['success' => true, 'data' => $article->fresh()]);
    }

    public function destroy(string $id)   // ← string, pas int (UUID)
    {
        Article::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Article supprimé.']);
    }
}
