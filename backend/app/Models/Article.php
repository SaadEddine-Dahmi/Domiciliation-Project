<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'domiciliataire_id',
        'title',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'id' => 'integer',
    ];

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    /**
     * Contracts that include this article clause.
     * Required by ArticleController::destroy(), which checks this relation
     * before allowing deletion (or detaches it) so removing an article
     * doesn't silently corrupt existing contract snapshots.
     */
    public function contrats()
    {
        return $this->belongsToMany(Contrat::class, 'contrat_articles')
            ->withPivot('ordre')
            ->withTimestamps();
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}
