<?php
// app/Http/Controllers/Concerns/SyncsArticles.php
//
// Shared pivot-sync logic used by both ContratController and
// TemplateController. Both resources attach an ordered list of Article
// records through a pivot table with an 'ordre' column, so the parsing
// and syncing logic is identical and lives here once.

namespace App\Http\Controllers\Concerns;

trait SyncsArticles
{
    /**
     * Sync the given model's articles() pivot with the provided article list.
     *
     * Accepted input formats:
     *   Object form: [ {id: "3", ordre: 1}, {id: "14", ordre: 2} ]
     *   Flat form:   [ 3, 14 ]
     *
     * Article PKs are integer auto-increment; cast each ID to (int).
     * IDs resolving to <= 0 are skipped silently.
     *
     * Eloquent sync() semantics:
     *   Detaches removed articles, attaches new ones, updates ordre for existing.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model  Must expose an articles() belongsToMany relation
     * @param  array                                $rawArticles
     */
    protected function syncArticlesList($model, array $rawArticles): void
    {
        if (empty($rawArticles)) {
            $model->articles()->sync([]);
            return;
        }

        $syncData = [];

        foreach ($rawArticles as $index => $item) {
            if (is_array($item)) {
                $articleId = (int) ($item['id']    ?? 0);
                $ordre     = (int) ($item['ordre'] ?? ($index + 1));
            } else {
                $articleId = (int) $item;
                $ordre     = $index + 1;
            }

            if ($articleId > 0) {
                $syncData[$articleId] = ['ordre' => $ordre];
            }
        }

        $model->articles()->sync($syncData);
    }
}
