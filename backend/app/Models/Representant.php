<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Representant extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
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

    // The entreprise this person represents. Enforced 1-to-1 by a
    // unique index on representants.entreprise_id.
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    // Audit trail of changes to this representant, newest first.
    public function history()
    {
        return $this->hasMany(RepresentantHistory::class, 'representant_id')
            ->orderByDesc('created_at');
    }

    // Full name for PDF/signature rendering, e.g. "YOUSSEF EL JADIANI".
    public function getNomCompletAttribute(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }
}