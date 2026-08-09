<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alerte extends Model
{
    use HasFactory;

    protected $fillable = ['contrat_id', 'date_alerte', 'envoye'];
    protected $casts = ['date_alerte' => 'date', 'envoye' => 'boolean'];

    // The contract this expiry alert was scheduled for.
    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    // Notifications generated from this alert (usually one, sent when due).
    public function notifications()
    {
        return $this->hasMany(AppNotification::class, 'alert_id');
    }
}