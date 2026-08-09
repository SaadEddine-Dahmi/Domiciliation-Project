<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Template extends Model
{
    use HasFactory;

    protected $fillable = ['domiciliataire_id', 'name', 'description'];

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    /**
     * Ordered set of articles attached to this template.
     * Pivot 'ordre' preserves the display order chosen by the domiciliataire
     * so it can be reproduced instantly when the template is loaded into
     * a new contract wizard.
     */
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'template_articles')
            ->withPivot('ordre')
            ->withTimestamps();
    }

    /**
     * Filter templates by tenant (domiciliataire).
     * Usage: Template::forTenant(auth()->id())->get()
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}
