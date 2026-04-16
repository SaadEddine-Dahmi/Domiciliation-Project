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
    protected static function booted()
    {
        static::creating(function ($facture) {
            $year = date('Y');

            // 1. Find the last record from the current year
            $lastFacture = self::whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastFacture && $lastFacture->numero_facture) {
                // 2. Extract the number part (e.g., from FAC-2026-005, get 005)
                $lastNumber = (int) substr($lastFacture->numero_facture, -3);
                $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            } else {
                // 3. Start at 001 if it's the first invoice of the year
                $newNumber = '001';
            }

            $facture->numero_facture = "FAC-{$year}-{$newNumber}";
        });
    }

    public function contrat() { return $this->belongsTo(Contrat::class); }
    public function entreprise() { return $this->belongsTo(Entreprise::class); }
    public function paiements() { return $this->hasMany(Paiement::class); }
}
