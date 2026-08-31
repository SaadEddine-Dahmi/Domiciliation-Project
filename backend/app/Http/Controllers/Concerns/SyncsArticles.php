<?php
// app/Http/Controllers/Concerns/SyncsArticles.php
// Syncs ordered article pivot data for contract-like resources.

namespace App\Http\Controllers\Concerns;

trait SyncsArticles
{
    // Accepts either flat IDs or objects containing id and ordre.
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
