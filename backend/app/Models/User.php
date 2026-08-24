<?php

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
        // True when the current password was system-generated (new
        // client account, or a domiciliataire-triggered reset) and the
        // owner has not yet chosen their own. See ClientController and
        // AuthController::changePassword().
        'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activation_date' => 'date',
        'approved_at' => 'datetime',
        'must_change_password' => 'boolean',
    ];

    // photo_url and initials are computed on every serialization of a
    // User (login response, /auth/me, client/admin listings, profile
    // endpoint) so the frontend never has to build avatar logic itself
    // — see UserAvatar.vue, which just reads these two fields.
    protected $appends = ['photo_url', 'initials'];

    // ── Relations ──────────────────────────────────────────

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

    // ── Accessors ──────────────────────────────────────────

    /**
     * Public URL for this user's profile photo, or null if none is set
     * (frontend falls back to initials in that case — see
     * UserAvatar.vue).
     *
     * FIX: previously built as url('/api/profile/photo/' . $this->id),
     * a path that was never registered in routes/api.php — every photo
     * request 404'd, and the frontend's broken-image fallback silently
     * swapped in initials, making the failure invisible from the UI.
     * Now built from the actual registered route name so the two can
     * never drift apart again.
     */
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
