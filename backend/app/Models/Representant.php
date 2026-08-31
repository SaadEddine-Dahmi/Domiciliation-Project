<?php
// app/Models/Representant.php
// Stores legal representative identity for users or client companies.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Representant extends Model
{
    use HasFactory;

    protected $fillable = [
        'representable_id',
        'representable_type',
        'nom',
        'prenom',
        'cin',
        'nationalite',
        'date_naissance',
        'adresse',
        'telephone',
        'email',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    public function representable()
    {
        return $this->morphTo();
    }

    public function history()
    {
        return $this->hasMany(RepresentantHistory::class, 'representant_id')
            ->orderByDesc('created_at');
    }

    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }
}
