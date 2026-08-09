<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DomiciliaireProfile extends Model
{
    use HasFactory;

    // Explicit table name — the class name "DomiciliaireProfile" does not
    // pluralize to "domiciliataire_profiles" (the actual table), so Eloquent's
    // naming convention guess is wrong without this override.
    protected $table = 'domiciliataire_profiles';

    protected $fillable = [
        'user_id',
        'nom_societe',
        'representant_legal',
        'identite_representant',
        'rc',
        'if_fiscal',
        'tp',
        'adresses',
    ];

    protected $casts = [
        'adresses' => 'array',
    ];

    // The domiciliataire user account this profile belongs to.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Returns adresses as a flat array of {label, value} objects,
    // falling back to an empty array when null.
    public function getAdressesListAttribute(): array
    {
        return is_array($this->adresses) ? $this->adresses : [];
    }
}