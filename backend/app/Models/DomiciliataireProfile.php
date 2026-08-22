<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Company-level identity of the domiciliation centre ONLY.
 *
 * Deliberately holds NO representative attributes (nom, cin, DOB,
 * nationality, contact) — those belong exclusively to the polymorphic
 * Representant linked to the owning User (see User::representant() via
 * HasRepresentant). Mixing the two here would recreate the exact
 * duplication problem this refactor removes.
 */
class DomiciliataireProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_societe',

        // Free text chosen by the domiciliataire — printed as the heading
        // on every generated contract. Falls back to a default title
        // when empty (see ContratController::store()).
        'contract_title',

        'rc',        // Registre de Commerce
        'if_fiscal', // Identifiant Fiscal
        'tp',        // Taxe Professionnelle

        // Array of {label, value}. Index 0 = siège social, every entry
        // after it = succursale (see ContratController::buildTokenMap()).
        'adresses',
    ];

    protected $casts = [
        'adresses' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAdressesListAttribute(): array
    {
        return is_array($this->adresses) ? $this->adresses : [];
    }
}