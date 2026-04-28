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

    // ── Relations ──────────────────────────────────────────

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function history()
    {
        return $this->hasMany(RepresentantHistory::class, 'representant_id')
            ->orderByDesc('created_at');
    }

    // ── Accessors ──────────────────────────────────────────

    /**
     * Full name used in PDF template rendering.
     * e.g. "YOUSSEF EL JADIANI"
     */
    public function getNomCompletAttribute(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }
}
