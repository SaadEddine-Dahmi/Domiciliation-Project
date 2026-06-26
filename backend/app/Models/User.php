<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'role',
        'status',
        'activation_date',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notification_preferences',
        'nom_societe',
        'representant_legal',
        'identite_representant',
        'rc',
        'if_fiscal',
        'tp',
        'adresses',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activation_date' => 'date',
        'approved_at'     => 'datetime',
        'adresses' => 'array',
    ];

    // ── Relations ──────────────────────────────────────────

    // Domiciliataire-owned resources
    public function entreprises()
    {
        return $this->hasMany(Entreprise::class, 'domiciliataire_id');
    }

    public function contrats()
    {
        return $this->hasMany(Contrat::class, 'domiciliataire_id');
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'domiciliataire_id');
    }

    // Client-linked entreprises
    public function clientEntreprises()
    {
        return $this->hasMany(Entreprise::class, 'client_user_id');
    }

    public function uploadedDocuments()
    {
        return $this->hasMany(Document::class, 'uploaded_by_user');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class, 'user_id');
    }

    // Who approved this account
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Returns true only if:
     * - status is 'active'
     * - activation_date is today or in the past (if set)
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->activation_date && $this->activation_date->isFuture()) {
            return false;
        }

        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDomiciliataire(): bool
    {
        return $this->role === 'domiciliataire';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

/**
 * Returns adresses as a flat array of {label, value} objects.
 * Falls back to empty array if null.
 * Used in the contract wizard address dropdown.
 */
public function getAdressesListAttribute(): array
{
    return is_array($this->adresses) ? $this->adresses : [];
}

/**
 * Profile completion check — true when all required company fields are set.
 */
public function hasCompleteProfile(): bool
{
    return !empty($this->nom_societe)
        && !empty($this->representant_legal)
        && !empty($this->adresses)
        && count($this->adresses) > 0;
}
}
