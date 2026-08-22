<?php

namespace App\Models\Concerns;

use App\Models\Representant;

/**
 * Grants any legal party (a domiciliataire User, or a client Entreprise)
 * exactly one polymorphic Representant.
 *
 * This is the single relation definition shared by both sides of a
 * contract — see Representant::representable() for the inverse.
 */
trait HasRepresentant
{
    public function representant()
    {
        return $this->morphOne(Representant::class, 'representable');
    }
}