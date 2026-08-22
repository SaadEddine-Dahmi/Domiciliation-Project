<?php

namespace App\Models;

use App\Models\Concerns\HasRepresentant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Pure authentication + account identity. Deliberately holds nothing
 * that belongs to a legal role:
 *   - Company data (nom_societe, RC, IF, TP, adresses) → DomiciliataireProfile
 *   - Legal representative identity (CIN, DOB, nationality, address)
 *     → Representant, via the polymorphic HasRepresentant trait below.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRepresentant;

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
        'email_alerts_enabled',
        'photo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activation_date' => 'date',
        'approved_at' => 'datetime',
        'email_alerts_enabled' => 'boolean',
    ];

    protected $appends = ['photo_url', 'initials'];

    // ── Relations ──────────────────────────────────────────

    /**
     * Company-level profile of the domiciliation centre. One-to-one,
     * may be null until the domiciliataire completes it for the first
     * time. Holds ONLY company data — never representative identity.
     */
    public function profile()
    {
        return $this->hasOne(DomiciliataireProfile::class, 'user_id');
    }

    // representant() is provided by HasRepresentant:
    //   morphOne(Representant::class, 'representable')

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

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Helpers ────────────────────────────────────────────

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
     * Profile completion check — requires both the company profile
     * (nom_societe, at least one adresse) AND a fully identified legal
     * representative (nom + cin on the polymorphic Representant record).
     *
     * FIX: previously read $profile->representant_legal, a column that
     * has been dropped from domiciliataire_profiles. Representative
     * identity now lives exclusively on the polymorphic Representant
     * relation, exactly the way it works for a client Entreprise.
     */
    public function hasCompleteProfile(): bool
    {
        $profile = $this->profile;
        $rep = $this->representant;

        return $profile
            && !empty($profile->nom_societe)
            && !empty($profile->adresses)
            && count($profile->adresses) > 0
            && $rep
            && !empty($rep->nom)
            && !empty($rep->cin);
    }

    // ── Photo / initials ─────────────────────────────────────

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        $host = (app()->bound('request') && request())
            ? rtrim(request()->getSchemeAndHttpHost(), '/')
            : rtrim((string) config('app.url'), '/');

        return "{$host}/api/users/{$this->id}/photo";
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_strtoupper(mb_substr(trim((string) $this->nom), 0, 1));
        $second = mb_strtoupper(mb_substr(trim((string) $this->prenom), 0, 1));

        $initials = $first . $second;

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($this->email, 0, 1));
    }
}