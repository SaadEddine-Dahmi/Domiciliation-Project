<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DomiciliataireProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_societe',
        'representant_legal',
        'identite_representant',

        // Contact details of the centre's legal representative — used on
        // the "D'une part" block of the contract PDF (see
        // ContratController::buildTokenMap()).
        'representant_email',
        'representant_telephone',

        'rc',
        'if_fiscal',
        'tp',

        // Array of {label, value}. Convention enforced at read time in
        // ContratController::buildTokenMap(): index 0 = siège social,
        // every subsequent entry = succursale.
        'adresses',
    ];

    protected $casts = [
        'adresses' => 'array',
    ];

    // ── Relations ──────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Accessors ──────────────────────────────────────────

    /**
     * Returns adresses as a flat array of {label, value} objects.
     * Falls back to empty array when null.
     */
    public function getAdressesListAttribute(): array
    {
        return is_array($this->adresses) ? $this->adresses : [];
    }
}