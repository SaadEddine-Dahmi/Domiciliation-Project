<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Facture extends Model
{
    protected $fillable = [
        'contrat_id',
        'entreprise_id',
        'domiciliataire_id',
        'montant_total',
        'statut',
        'date_facture',
        'date_echeance',
        'numero_facture',
    ];

    protected $casts = [
        'date_facture'  => 'date',
        'date_echeance' => 'date',
        'montant_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Facture $facture) {
            // Ensure tenant id is stored for fast filtering (optional but recommended)
            if (!$facture->domiciliataire_id && $facture->contrat_id) {
                $facture->domiciliataire_id = Contrat::whereKey($facture->contrat_id)->value('domiciliataire_id');
            }

            // Generate unique invoice number: FAC-YYYY-###
            $facture->numero_facture = DB::transaction(function () {
                $year = date('Y');

                $last = static::whereYear('created_at', $year)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();

                $seq = 0;
                if ($last && $last->numero_facture) {
                    $seq = (int) substr($last->numero_facture, -3);
                }

                return sprintf('FAC-%s-%03d', $year, $seq + 1);
            });
        });
    }

    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}
