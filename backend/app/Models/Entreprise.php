<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;

class Entreprise extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'domiciliataire_id',
        'client_user_id',
        'raison_sociale',
        'forme_juridique',
        'adresse',
        'ville',
        'pays',
        'capital',
        'date_creation',
        'statut',
    ];

    protected $casts = [
        'date_creation' => 'date',
        'capital'       => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    public function clientUser()
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /**
     * Each entreprise has exactly ONE representant.
     * Enforced by UNIQUE constraint on representants.entreprise_id.
     */
    public function representant()
    {
        return $this->hasOne(Representant::class);
    }

    public function contrats()
    {
        return $this->hasMany(Contrat::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function courriers()
    {
        return $this->hasMany(Courrier::class);
    }

    public function factures()
    {
        return $this->hasMany(Facture::class);
    }
}
