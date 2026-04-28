<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;

class Article extends Model
{
    use HasUuids;

    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id',
        'domiciliataire_id', // tenant owner
        'title',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    /**
     * Filter articles by tenant (domiciliataire).
     * Usage: Article::forTenant(auth()->id())->get()
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}
