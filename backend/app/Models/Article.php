<?php
// app/Models/Article.php
//
// Article clause templates, owned by a domiciliataire (tenant-scoped).
// PK: bigint auto-increment — matches the `articles` migration and every
// pivot table that references article_id (contrat_articles, template_articles).
// A previous revision incorrectly used HasUuids on this model while the
// migration created an integer PK, which made every INSERT fail with
// "invalid input syntax for type bigint" — articles were never persisted.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Article extends Model
{
    use HasFactory;

    // Explicit, because some PostgreSQL driver combinations otherwise
    // serialise `id` as 0 in nested eager-loads, which breaks chip
    // selection on the frontend (selectedArticleIds.includes(String(id))
    // matching every row at once).
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'domiciliataire_id', // tenant owner — never client-supplied
        'title',
        'body',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    /** Article::forTenant($id)->get() — the only way articles are queried */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}