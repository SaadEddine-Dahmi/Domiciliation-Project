<?php
// app/Models/Article.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory; // ← ADD THIS
use Illuminate\Database\Eloquent\Builder;

class Article extends Model
{
    use HasUuids;
    use HasFactory; // ← ADD THIS

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'domiciliataire_id',
        'title',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('domiciliataire_id', $tenantId);
    }
}