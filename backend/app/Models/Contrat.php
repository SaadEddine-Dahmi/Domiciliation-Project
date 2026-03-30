<?php
// ============================================================
// app/Models/Contrat.php
// State machine : draft → active → expired / terminated
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contrat extends Model
{
    use HasFactory;

    protected $fillable = [
        'domiciliataire_id',
        'entreprise_id',
        'date_signature',
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
        'date_signature' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'next_alert_date' => 'date',
        'prix_mensuel' => 'decimal:2',
        'prix_total' => 'decimal:2',
        'caution' => 'decimal:2',
    ];

    // ── Relations ─────────────────────────────────────────
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

    // ── State machine helpers ─────────────────────────────

    /** Passe le contrat en actif + calcule next_alert_date */
    public function activate(): void
    {
        $delayMonths = $this->notification_delay_months ?? 1;
        $alertDate = $this->date_fin
            ? $this->date_fin->copy()->subMonths($delayMonths)
            : null;

        $this->update([
            'statut' => 'active',
            'next_alert_date' => $alertDate,
        ]);

        // Créer l'alerte en DB pour le cron
        if ($alertDate) {
            $this->alertes()->create([
                'date_alerte' => $alertDate,
                'envoye' => false,
            ]);
        }
    }

    /** Expire le contrat (appelé par le cron) */
    public function expire(): void
    {
        $this->update(['statut' => 'expired']);
    }

    /** Résilie le contrat manuellement */
    public function terminate(): void
    {
        $this->update(['statut' => 'terminated']);
    }

    /** Vérifie si le contrat est visible par le client */
    public function isVisibleToClient(): bool
    {
        return $this->statut === 'active';
    }
}