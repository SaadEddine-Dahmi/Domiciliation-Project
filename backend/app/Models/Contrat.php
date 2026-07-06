<?php
// app/Models/Contrat.php
//
// Represents a domiciliation contract between the service provider
// (domiciliataire) and a client company (entreprise).
//
// titre_contrat is in $fillable so the dynamic title typed by the person
// creating the contract actually persists — every create()/update() call
// in the controller writes this field via mass assignment, and Eloquent
// silently drops any field missing from $fillable.
//
// This file also implements the renewal feature on top of the existing
// state machine (draft -> active -> expired / terminated):
//
//   renewedFrom()  - belongsTo: the contract this one was renewed from
//   renewedTo()    - hasOne: the contract that renewed THIS one, if any
//   isRenewable()  - business rule used by the controller and the UI
//   isRenewal()    - true if this contract itself is the result of a renewal
//
// titre_contrat:
//   Fully dynamic. The person creating the contract types the exact title
//   printed at the top of the PDF. No title is hardcoded — only a neutral
//   fallback string ('Contrat de Domiciliation', the generic French legal
//   name for this document type, not a product or brand name) is applied
//   when the field arrives empty.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contrat extends Model
{
    use HasFactory;

    protected $fillable = [
        'renewed_from_id',          // set only by ContratController::renew()
        'domiciliataire_id',
        'entreprise_id',
        'instruction_no',
        'titre_contrat',            // dynamic title chosen by the person creating the contract
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
        'date_signature' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'next_alert_date' => 'date',
        'prix_mensuel' => 'decimal:2',
        'prix_total' => 'decimal:2',
        'caution' => 'decimal:2',
    ];

    // ── Relations ────────────────────────────────────────────────────────

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
     * The contract this one was renewed FROM (the predecessor).
     * Null on an original, non-renewed contract.
     */
    public function renewedFrom()
    {
        return $this->belongsTo(Contrat::class, 'renewed_from_id');
    }

    /**
     * The contract that renewed THIS one (the successor).
     * Null if this contract has not been renewed yet.
     *
     * hasOne is correct here because isRenewable() enforces "a contract
     * can be renewed at most once".
     */
    public function renewedTo()
    {
        return $this->hasOne(Contrat::class, 'renewed_from_id');
    }

    // ── State machine ────────────────────────────────────────────────────

    /**
     * Transition draft -> active.
     * Calculates next_alert_date based on notification_delay_months and
     * creates an alerte record consumed by the notification cron job.
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
     * Transition active -> expired.
     * Called exclusively by ExpireContractsCommand (scheduled daily in
     * routes/console.php) — never triggered manually from a controller,
     * since expiration is a time-based fact, not a user action.
     */
    public function expire(): void
    {
        $this->update(['statut' => 'expired']);
    }

    /**
     * Transition active -> terminated.
     * Called manually by the domiciliataire (early termination).
     */
    public function terminate(): void
    {
        $this->update(['statut' => 'terminated']);
    }

    /**
     * Only active contracts are visible to the client portal.
     */
    public function isVisibleToClient(): bool
    {
        return $this->statut === 'active';
    }

    // ── Renewal guards ───────────────────────────────────────────────────

    /**
     * Business rule consulted by both the "Renew" button in the UI and
     * the renew() endpoint before creating a new contract.
     *
     * A contract can be renewed only if:
     *   - its status is active or expired (a draft has no completed
     *     period to continue from, and a terminated contract was ended
     *     early by choice, not naturally expired)
     *   - it has not already been renewed (renewedTo does not exist yet)
     */
    public function isRenewable(): bool
    {
        return in_array($this->statut, ['active', 'expired'], true)
            && $this->renewedTo()->doesntExist();
    }

    /** True if this contract itself is the result of a renewal. */
    public function isRenewal(): bool
    {
        return $this->renewed_from_id !== null;
    }
}