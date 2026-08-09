<?php

namespace App\Models;

use App\Models\DomiciliaireProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Company/profile fields (nom_societe, rc, if_fiscal, tp,
    // representant_legal, identite_representant, adresses) live on
    // domiciliataire_profiles, not here — see profile() below.
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
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activation_date' => 'date',
        'approved_at' => 'datetime',
        'email_alerts_enabled' => 'boolean',
    ];

    // Entreprises owned by this domiciliataire (tenant).
    public function entreprises()
    {
        return $this->hasMany(Entreprise::class, 'domiciliataire_id');
    }

    // Contracts created by this domiciliataire.
    public function contrats()
    {
        return $this->hasMany(Contrat::class, 'domiciliataire_id');
    }

    // Article-grouping templates owned by this domiciliataire.
    public function templates()
    {
        return $this->hasMany(Template::class, 'domiciliataire_id');
    }

    // Entreprises this user is the client-portal login for.
    public function clientEntreprises()
    {
        return $this->hasMany(Entreprise::class, 'client_user_id');
    }

    // Documents this user uploaded (any role).
    public function uploadedDocuments()
    {
        return $this->hasMany(Document::class, 'uploaded_by_user');
    }

    // Notifications/messages addressed to this user.
    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class, 'user_id');
    }

    // The admin who approved this account, if any.
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // One-to-one company profile, meaningful only for role = domiciliataire.
    // May be null until the domiciliataire saves their profile once.
    public function profile()
    {
        return $this->hasOne(DomiciliaireProfile::class, 'user_id');
    }

    // True only if status is 'active' AND activation_date (if set) has passed.
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

    // Role helper: true if this account is the platform super-admin.
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Role helper: true if this account is a service-provider tenant.
    public function isDomiciliataire(): bool
    {
        return $this->role === 'domiciliataire';
    }

    // Role helper: true if this account is a domiciled client login.
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    // True when all required company profile fields are filled in on the
    // related domiciliataire_profiles row. False (not an error) if no
    // profile row exists yet — used to drive the frontend completion banner.
    public function hasCompleteProfile(): bool
    {
        $profile = $this->profile;

        if (!$profile) {
            return false;
        }

        return !empty($profile->nom_societe)
            && !empty($profile->representant_legal)
            && !empty($profile->adresses)
            && count($profile->adresses) > 0;
    }
}