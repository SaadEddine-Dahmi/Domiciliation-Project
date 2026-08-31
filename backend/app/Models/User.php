<?php
// app/Models/User.php
// Represents an authenticated platform user and role-specific account state.

namespace App\Models;

use App\Models\Concerns\HasRepresentant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

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
        'photo_path',
        'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activation_date' => 'date',
        'approved_at' => 'datetime',
        'must_change_password' => 'boolean',
    ];

    protected $appends = ['photo_url', 'initials', 'unread_notifications_count'];

    public function profile()
    {
        return $this->hasOne(DomiciliataireProfile::class, 'user_id');
    }

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

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path || !Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        return route('users.photo', [
            'id' => $this->id,
            'v' => substr(sha1($this->photo_path), 0, 12),
        ]);
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_strtoupper(mb_substr(trim((string) $this->nom), 0, 1));
        $second = mb_strtoupper(mb_substr(trim((string) $this->prenom), 0, 1));
        $initials = $first . $second;

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr((string) $this->email, 0, 1));
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        if (!$this->exists) {
            return 0;
        }

        try {
            return $this->appNotifications()
                ->whereNull('from_user_id')
                ->where('is_read', false)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return !$this->activation_date || !$this->activation_date->isFuture();
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

    public function hasCompleteProfile(): bool
    {
        $profile = $this->profile;

        return $profile
            && !empty($profile->nom_societe)
            && !empty($profile->adresses)
            && count($profile->adresses) > 0
            && $this->representant?->nom
            && $this->representant?->cin;
    }
}
