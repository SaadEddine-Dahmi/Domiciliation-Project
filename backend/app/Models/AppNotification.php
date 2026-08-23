<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasFactory;

    // FIX: the real migration (2026_01_01_000016_create_notifications_table.php)
    // creates a table called `notifications`, not `app_notifications`. Every
    // AppNotification::create(...) call in the app was silently failing
    // with a QueryException because of this mismatch.
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'from_user_id',
        'alert_id',
        'contrat_id',
        'subject',
        'message',
        'type',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function alerte()
    {
        return $this->belongsTo(Alerte::class, 'alert_id');
    }

    public function contrat()
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }
}