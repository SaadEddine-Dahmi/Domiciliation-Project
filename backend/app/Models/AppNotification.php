<?php
// Backs the `notifications` table, used for BOTH system notifications
// (contract expiry, payment received) and direct domiciliataire → client
// messages. from_user_id = null means a system notification;
// from_user_id set means a direct message from that sender.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'from_user_id',
        'alert_id',
        'contrat_id',
        'message',
        'subject',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // The recipient of this notification/message.
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // The sender, or null if this is a system-generated notification.
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    // Alias of user() for readability on the domiciliataire's sent-messages view.
    public function toUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // The scheduled alert this notification was generated from, if any.
    public function alerte()
    {
        return $this->belongsTo(Alerte::class, 'alert_id');
    }

    // The contract this notification relates to, if any.
    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    // True if this row is a direct message rather than a system notification.
    public function isMessage(): bool
    {
        return $this->from_user_id !== null;
    }
}