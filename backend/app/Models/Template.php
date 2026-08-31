<?php
// app/Models/Template.php
// Represents an ordered reusable contract template for a tenant.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = ['domiciliataire_id', 'name', 'description'];

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'template_articles')
            ->withPivot('ordre')
            ->withTimestamps();
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}
