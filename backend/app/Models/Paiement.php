<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Paiement extends Model
{
    use HasFactory;

    protected $fillable = ['facture_id', 'montant', 'date_paiement', 'mode_paiement'];
    protected $casts = ['date_paiement' => 'date', 'montant' => 'decimal:2'];

    // The invoice this payment was made against.
    public function facture()
    {
        return $this->belongsTo(Facture::class);
    }
}