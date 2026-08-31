<?php
// app/Services/ArticleLibraryService.php
// Handles tenant-scoped article library writes and delete safety.

namespace App\Services;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ArticleLibraryService
{
    public function create(User $user, array $data): Article
    {
        return DB::transaction(fn() => Article::create([
            'domiciliataire_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    public function update(Article $article, array $data): Article
    {
        return DB::transaction(function () use ($article, $data) {
            $article->update($data);

            return $article->fresh();
        });
    }

    public function delete(Article $article): bool
    {
        if ($this->isUsed($article)) {
            return false;
        }

        try {
            DB::transaction(fn() => $article->delete());
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    private function isUsed(Article $article): bool
    {
        return DB::table('contrat_articles')->where('article_id', $article->id)->exists()
            || DB::table('template_articles')->where('article_id', $article->id)->exists();
    }
}
