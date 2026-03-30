<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($this->getTable().'.domiciliataire_id', $tenantId);
    }
}
