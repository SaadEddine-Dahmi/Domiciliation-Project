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
        'archived_at',
    ];

    protected $casts = [
        'date_facture'  => 'date',
        'date_echeance' => 'date',
        'montant_total' => 'decimal:2',
        'archived_at'   => 'datetime',
    ];

    // On creation: backfills domiciliataire_id from the parent contract
    // (denormalised for fast tenant-scoped queries), and generates a
    // unique sequential invoice number in the format FAC-YYYY-###.
    protected static function booted(): void
    {
        static::creating(function (Facture $facture) {
            if (!$facture->domiciliataire_id && $facture->contrat_id) {
                $facture->domiciliataire_id = Contrat::whereKey($facture->contrat_id)->value('domiciliataire_id');
            }

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

    // The contract this invoice was generated for.
    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    // The client entreprise being billed.
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    // The domiciliataire (tenant) this invoice belongs to.
    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    // Individual payment records made against this invoice.
    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}
