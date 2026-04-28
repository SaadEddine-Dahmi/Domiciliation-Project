<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Facture extends Model
{
    protected $fillable = [
        'contrat_id',
        'domiciliataire_id',
        'montant',
        'statut',
        'date_echeance',
        'numero_facture',
    ];

    protected $casts = [
        'date_echeance' => 'date',
        'montant'       => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Facture $facture) {
            // The unique index on numero_facture is the final safety net.
            $facture->numero_facture = DB::transaction(function () {
                $year = date('Y');

                // Lock the latest row for this year — prevents race condition
                $last = static::whereYear('created_at', $year)
                    ->lockForUpdate()
                    ->orderBy('id', 'desc')
                    ->first();

                if ($last && $last->numero_facture) {
                    // Extract the 3-digit sequence from e.g. FAC-2026-005
                    $seq = (int) substr($last->numero_facture, -3);
                } else {
                    $seq = 0;
                }

                return sprintf('FAC-%s-%03d', $year, $seq + 1);
            });
        });
    }

    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }
}
