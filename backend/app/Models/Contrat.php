<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contrat extends Model
{
    use HasFactory;

    // NOTE: pdf_path is intentionally NOT fillable anymore. Contracts are no
    // longer persisted to disk as PDF files — every preview/download request
    // renders the document live from current database state. See
    // ContratController::streamPdf(). scanned_pdf_path is kept: it stores the
    // signed/legalised paper contract once the domiciliataire scans it back in.
    protected $fillable = [
        'domiciliataire_id',
        'entreprise_id',
        'instruction_no',
        'titre_contrat',
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

    /**
     * Chronological audit trail of every create/update/delete performed
     * on this contract. Newest entries first.
     */
    public function history()
    {
        return $this->hasMany(ContratHistory::class, 'contrat_id')
            ->orderByDesc('created_at');
    }

    // ── Audit trail hook ───────────────────────────────────

    /**
     * Writes a ContratHistory snapshot before every update and delete.
     *
     * updating(): captures getOriginal() — the row's DB state right before
     * the new values are written — plus getDirty() to know exactly which
     * columns changed in this request.
     *
     * deleting(): captures the full current attribute set, since after
     * deletion there is nothing left to compare against.
     */
    protected static function booted(): void
    {
        static::updating(function (Contrat $contrat) {
            $changed = array_keys($contrat->getDirty());
            if (empty($changed)) {
                return;
            }

            ContratHistory::create([
                'contrat_id' => $contrat->id,
                'changed_by' => auth()->id(),
                'data' => $contrat->getOriginal(),
                'changed_fields' => $changed,
                'action' => 'update',
            ]);
        });

        static::deleting(function (Contrat $contrat) {
            ContratHistory::create([
                'contrat_id' => $contrat->id,
                'changed_by' => auth()->id(),
                'data' => $contrat->getAttributes(),
                'changed_fields' => array_keys($contrat->getAttributes()),
                'action' => 'delete',
            ]);
        });
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
        $alertDate = $this->date_fin
            ? $this->date_fin->copy()->subMonths($delayMonths)
            : null;

        $this->update([
            'statut' => 'active',
            'next_alert_date' => $alertDate,
        ]);

        if ($alertDate) {
            $this->alertes()->create([
                'date_alerte' => $alertDate,
                'envoye' => false,
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
     * True once the domiciliataire has uploaded the scanned, legally signed
     * copy of this contract. From this point on, the contract's data fields
     * are frozen — see ContratController::assertEditable().
     */
    public function isLegalised(): bool
    {
        return !empty($this->scanned_pdf_path);
    }

    /**
     * Only active contrats are visible to clients.
     */
    public function isVisibleToClient(): bool
    {
        return $this->statut === 'active';
    }
}