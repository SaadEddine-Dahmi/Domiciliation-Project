<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contrat extends Model
{
    use HasFactory;

    protected $fillable = [
        'domiciliataire_id',
        'entreprise_id',
        'instruction_no',
        'date_signature',
        'ville_signature',
        'date_debut',
        'date_fin',
        'duree_mois',
        'prix_mensuel',
        'prix_total',
        'caution',
        'mode_paiement',
        'statut',
        'pdf_path',
        'scanned_pdf_path',
        'notification_delay_months',
        'next_alert_date',
    ];

    protected $casts = [
        'date_signature'  => 'date',
        'date_debut'      => 'date',
        'date_fin'        => 'date',
        'next_alert_date' => 'date',
        'prix_mensuel'    => 'decimal:2',
        'prix_total'      => 'decimal:2',
        'caution'         => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function alertes()
    {
        return $this->hasMany(Alerte::class);
    }

    public function factures()
    {
        return $this->hasMany(Facture::class);
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'contrat_articles')
            ->withPivot('ordre')
            ->withTimestamps();
    }

    // ── State machine ──────────────────────────────────────

    /**
     * Transition draft → active.
     * Calculates next_alert_date based on notification_delay_months.
     * Creates an alerte record for the cron job.
     */
    public function activate(): void
    {
        $delayMonths = $this->notification_delay_months ?? 1;
        $alertDate   = $this->date_fin
            ? $this->date_fin->copy()->subMonths($delayMonths)
            : null;

        $this->update([
            'statut'          => 'active',
            'next_alert_date' => $alertDate,
        ]);

        if ($alertDate) {
            $this->alertes()->create([
                'date_alerte' => $alertDate,
                'envoye'      => false,
            ]);
        }
    }

    /**
     * Transition active → expired.
     * Called by the daily cron job.
     */
    public function expire(): void
    {
        $this->update(['statut' => 'expired']);
    }

    /**
     * Transition active → terminated.
     * Called manually by the domiciliataire.
     */
    public function terminate(): void
    {
        $this->update(['statut' => 'terminated']);
    }

    /**
     * Only active contrats are visible to clients.
     */
    public function isVisibleToClient(): bool
    {
        return $this->statut === 'active';
    }
}
