<?php
// app/Models/Concerns/HasRepresentant.php
// Adds a single polymorphic legal representative relation.

namespace App\Models\Concerns;

use App\Models\Representant;

trait HasRepresentant
{
    public function representant()
    {
        return $this->morphOne(Representant::class, 'representable');
    }
}
