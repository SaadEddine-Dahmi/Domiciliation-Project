<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    use HasFactory;

    // Aligned to migration 2026_03_16_113451_create_factures_table.php
    protected $fillable = [
        'contrat_id',
        'entreprise_id',
        'numero_facture',
        'date_facture',
        'montant_total',
        'statut',
    ];

    protected $casts = [
        'date_facture' => 'date',
        'montant_total' => 'decimal:2',
    ];

    public function contrat() { return $this->belongsTo(Contrat::class); }
    public function entreprise() { return $this->belongsTo(Entreprise::class); }
    public function paiements() { return $this->hasMany(Paiement::class); }
}
