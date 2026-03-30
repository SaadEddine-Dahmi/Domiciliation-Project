<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Courrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id','expediteur','objet','date_reception','date_retrait','statut'
    ];

    protected $casts = [
        'date_reception' => 'date',
        'date_retrait' => 'date',
    ];

    public function entreprise() { return $this->belongsTo(Entreprise::class); }
}
