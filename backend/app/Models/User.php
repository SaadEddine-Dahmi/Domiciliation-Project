<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
        'email_alerts_enabled',
        'photo_path', // relative path on the "public" disk to the profile photo
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activation_date' => 'date',
        'approved_at' => 'datetime',
        'email_alerts_enabled' => 'boolean',
    ];

    /**
     * Always included when this model is serialized to JSON, so every
     * endpoint that returns a user (auth, admin lists, profile, etc.)
     * automatically carries a ready-to-use photo_url and initials —
     * no extra queries or per-endpoint mapping needed.
     */
    protected $appends = ['photo_url', 'initials'];

    // ── Relations ──────────────────────────────────────────

    /**
     * The domiciliataire's company profile (nom société, RC, IF, TP,
     * addresses, legal representative contact, contract title). One-to-one,
     * may be null until the domiciliataire completes their profile for
     * the first time.
     */
    public function profile()
    {
        return $this->hasOne(DomiciliataireProfile::class, 'user_id');
    }

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
     * Profile completion check — true when all required company fields on
     * the domiciliataire_profiles record are set.
     *
     * Deliberately does NOT require representant_email/representant_telephone
     * — those are optional contact details, not a hard prerequisite for
     * generating a valid contract. The profile photo is likewise optional
     * and never affects this check.
     */
    public function hasCompleteProfile(): bool
    {
        $profile = $this->profile;

        return $profile
            && !empty($profile->nom_societe)
            && !empty($profile->representant_legal)
            && !empty($profile->adresses)
            && count($profile->adresses) > 0;
    }

    // ── Photo / initials ─────────────────────────────────────

    /**
     * Public URL of the profile photo, or null if none was uploaded.
     *
     * FIX: previously built via Storage::disk('public')->url(), which
     * points at the "public" disk's symlinked /storage/... path. That
     * silently breaks whenever `php artisan storage:link` hasn't been run,
     * or whenever APP_URL doesn't match a host the browser can actually
     * reach (common in Docker, where APP_URL is often an internal-only
     * service hostname) — the <img> then fails to load with no visible
     * error.
     *
     * Now it points at a dedicated streaming route (see
     * DomiciliataireProfileController::photo()) and is built from the
     * CURRENT REQUEST's own host, not from the APP_URL config value. That
     * guarantees the URL always matches an origin the browser can reach:
     * it's the same origin that successfully served the request asking
     * for this attribute in the first place.
     */
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

    /**
     * Two-letter fallback shown in the UI whenever no photo is set:
     * first letter of "nom" + first letter of "prenom", uppercase.
     * If prenom is empty, only the nom initial is returned. If both are
     * somehow empty, falls back to the first letter of the email so the
     * avatar is never blank.
     */
    public function getInitialsAttribute(): string
    {
        $first = mb_strtoupper(mb_substr(trim((string) $this->nom), 0, 1));
        $second = mb_strtoupper(mb_substr(trim((string) $this->prenom), 0, 1));

        $initials = $first . $second;

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($this->email, 0, 1));
    }
}