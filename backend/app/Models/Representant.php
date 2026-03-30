<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Representant extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id','nom','prenom','cin','nationalite','date_naissance',
        'adresse','telephone','email',
    ];

    protected $casts = ['date_naissance' => 'date'];

    public function entreprise() { return $this->belongsTo(Entreprise::class); }
}
