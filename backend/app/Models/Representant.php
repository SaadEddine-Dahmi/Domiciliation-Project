<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A physical person acting as legal representative of an entity.
 *
 * Polymorphic on purpose: the SAME shape of data (CIN/passport, full
 * name, DOB, nationality, address, contact) applies whether the entity
 * being represented is:
 *   - a domiciliataire's own centre (representable = App\Models\User)
 *   - a client's entreprise domiciliée (representable = App\Models\Entreprise)
 *
 * This is the single source of truth for representative identity across
 * the whole app — `users` and `domiciliataire_profiles` never duplicate
 * any of these columns.
 */
class Representant extends Model
{
    use HasFactory;

    protected $fillable = [
        'representable_id',
        'representable_type',
        'nom',
        'prenom',
        'cin', // CIN or passport number
        'nationalite',
        'date_naissance',
        'adresse',
        'telephone',
        'email',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    // ── Relations ──────────────────────────────────────────

    /**
     * The entity this person legally represents — either a User
     * (domiciliataire) or an Entreprise (client company).
     */
    public function representable()
    {
        return $this->morphTo();
    }

    /**
     * Audit trail of changes to this representative's identity,
     * newest first. Shared history table regardless of which side
     * (domiciliataire or client) this representant belongs to.
     */
    public function history()
    {
        return $this->hasMany(RepresentantHistory::class, 'representant_id')
            ->orderByDesc('created_at');
    }

    // ── Accessors ──────────────────────────────────────────

    /**
     * Full name for PDF/signature rendering, e.g. "YOUSSEF EL JADIANI".
     * Used identically for both the domiciliataire's and the client's
     * representative on the contract template.
     */
    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }
}