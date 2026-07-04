<?php
// app/Models/Article.php
//
// Article = reusable contract clause template owned by a domiciliataire.
//
// PRIMARY KEY: integer auto-increment (bigint), matching the BIGSERIAL column
// produced by $table->id() in the articles migration.
//
// WHY HasUuids is NOT used here:
//   The migration uses $table->id() → BIGSERIAL / bigint unsigned.
//   HasUuids would override $incrementing = false and $keyType = 'string',
//   causing Eloquent to generate a UUID string for every new row. PostgreSQL
//   then tries to insert that UUID string into a bigint column and throws
//   "invalid input syntax for type bigint". Additionally, when articles are
//   eager-loaded as part of a Contrat, the missing 'id' cast caused the id
//   to serialise as 0 in JSON, making every chip appear selected at once
//   in the wizard (String(0) === String(0) for every article).
//
// CASTS:
//   'id' => 'integer' forces consistent serialisation in ALL JSON responses,
//   including nested eager-loads. Without it, the integer PK was sometimes
//   serialised as the string "0" or the integer 0.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Article extends Model
{
    // Integer auto-increment PK — must match the migration BIGSERIAL column.
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        // Do NOT include 'id' here — auto-increment PKs must never be
        // mass-assigned. The database generates the value on INSERT.
        'domiciliataire_id',
        'title',
        'body',
        'is_active',
    ];

    protected $casts = [
        // Force the integer PK to always serialise as a PHP int in JSON.
        // Without this cast, nested eager-loads (e.g. contrat->articles)
        // sometimes serialised id as 0, causing the wizard chip selector
        // to evaluate selectedArticleIds.includes(String(0)) === true for
        // every article simultaneously.
        'id' => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    /**
     * The domiciliataire who owns this article clause template.
     */
    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    /**
     * Contracts that include this article clause via the pivot table.
     */
    public function contrats()
    {
        return $this->belongsToMany(Contrat::class, 'contrat_articles')
            ->withPivot('ordre')
            ->withTimestamps();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Restrict results to articles owned by the given domiciliataire.
     *
     * Usage: Article::forTenant(auth()->id())->get()
     *
     * This scope is the primary IDOR guard. Every query in ArticleController
     * passes through it so a domiciliataire can never read or modify another
     * tenant's article library.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}