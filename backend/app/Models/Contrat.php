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
        'renewed_from_id',
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
        'archived_at',
        'notification_delay_months',
        'next_alert_date',
    ];

    protected $casts = [
        'date_signature' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'next_alert_date' => 'date',
        'archived_at' => 'datetime',
        'prix_mensuel' => 'decimal:2',
        'prix_total' => 'decimal:2',
        'caution' => 'decimal:2',
    ];

    /**
     * FIX: Eloquent's default JSON serialization for 'date'-cast attributes
     * emits a full ISO-8601 datetime string (e.g.
     * "2026-08-18T00:00:00.000000Z"). Native <input type="date"> elements
     * in the Nuxt wizard reject anything that isn't strictly "yyyy-MM-dd",
     * silently failing to populate date_debut/date_fin/date_signature when
     * resuming a draft or renewal — this is the browser console warning
     * "does not conform to the required format, yyyy-MM-dd".
     *
     * Overriding serializeDate() applies to every date-cast attribute on
     * this model (date_debut, date_fin, date_signature, next_alert_date)
     * everywhere the model is serialized to JSON — API responses, Eloquent
     * ->toArray(), etc. — without needing per-field formatting anywhere else.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

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

    public function history()
    {
        return $this->hasMany(ContratHistory::class, 'contrat_id')
            ->orderByDesc('created_at');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(Contrat::class, 'renewed_from_id');
    }

    public function renewals()
    {
        return $this->hasMany(Contrat::class, 'renewed_from_id');
    }

    // ── Audit trail hook ───────────────────────────────────

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

    public function expire(): void
    {
        $this->update(['statut' => 'expired']);
    }

    public function terminate(): void
    {
        $this->update(['statut' => 'terminated']);
    }

    public function setStatutAttribute($value): void
    {
        $this->attributes['statut'] = $value === 'brouillon' ? 'draft' : $value;
    }

    public function archive(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }

    public function restoreArchive(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }

    public function isLegalised(): bool
    {
        return !empty($this->scanned_pdf_path);
    }

    public function isVisibleToClient(): bool
    {
        return $this->statut === 'active' && $this->archived_at === null;
    }

    // ── Renewal ──────────────────────────────────────────────

    public function hasOpenRenewal(): bool
    {
        return $this->renewals()->whereIn('statut', ['draft', 'active'])->exists();
    }

    public function isRenewable(): bool
    {
        return in_array($this->statut, ['active', 'expired'], true)
            && !$this->hasOpenRenewal();
    }

    public function renew(): Contrat
    {
        if ($this->hasOpenRenewal()) {
            throw new \DomainException('Ce contrat a déjà un renouvellement en cours.');
        }

        if (!in_array($this->statut, ['active', 'expired'], true)) {
            throw new \DomainException('Seul un contrat actif ou expiré peut être renouvelé.');
        }

        $newStart = $this->date_fin
            ? $this->date_fin->copy()->addDay()
            : now();

        $newEnd = $this->duree_mois
            ? $newStart->copy()->addMonths($this->duree_mois)->subDay()
            : null;

        $draft = static::create([
            'domiciliataire_id' => $this->domiciliataire_id,
            'entreprise_id' => $this->entreprise_id,
            'renewed_from_id' => $this->id,
            'titre_contrat' => $this->titre_contrat,
            'date_debut' => $newStart,
            'date_fin' => $newEnd,
            'duree_mois' => $this->duree_mois,
            'prix_mensuel' => $this->prix_mensuel,
            'prix_total' => $this->prix_total,
            'caution' => $this->caution,
            'mode_paiement' => $this->mode_paiement,
            'ville_signature' => $this->ville_signature,
            'date_signature' => $this->date_signature,
            'notification_delay_months' => $this->notification_delay_months,
            'statut' => 'draft',
        ]);

        $articleSync = $this->articles()
            ->orderBy('contrat_articles.ordre')
            ->get()
            ->mapWithKeys(fn($a) => [$a->id => ['ordre' => $a->pivot->ordre]])
            ->toArray();

        $draft->articles()->sync($articleSync);

        return $draft;
    }
}
