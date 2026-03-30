<?php
// ============================================================
// app/Models/AppNotification.php
// Utilisé pour notifications système ET messages directs
// from_user_id = null  → notification système (alerte, paiement...)
// from_user_id != null → message direct domiciliataire → client
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'from_user_id', 'alert_id', 'contrat_id',
        'message', 'subject', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Destinataire
    public function user()     { return $this->belongsTo(User::class, 'user_id'); }
    // Expéditeur (null si notification système)
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    // Alias pour la lisibilité côté domiciliataire
    public function toUser()   { return $this->belongsTo(User::class, 'user_id'); }

    public function alerte()   { return $this->belongsTo(Alerte::class, 'alert_id'); }
    public function contrat()  { return $this->belongsTo(Contrat::class); }

    /** Vrai si c'est un message direct (pas une notification système) */
    public function isMessage(): bool
    {
        return $this->from_user_id !== null;
    }
}
