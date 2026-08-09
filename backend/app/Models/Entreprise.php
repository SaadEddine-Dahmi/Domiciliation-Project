<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;

class Entreprise extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'domiciliataire_id',
        'client_user_id',
        'raison_sociale',
        'forme_juridique',
        'adresse',
        'ville',
        'pays',
        'capital',
        'date_creation',
        'statut', // 'actif' | 'inactif' — controls client portal access, see AuthController::login()
    ];

    protected $casts = [
        'date_creation' => 'date',
        'capital' => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────

    public function domiciliataire()
    {
        return $this->belongsTo(User::class, 'domiciliataire_id');
    }

    public function clientUser()
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function history()
    {
        return $this->hasMany(EntrepriseHistory::class, 'entreprise_id')
            ->orderByDesc('created_at');
    }

    /**
     * Each entreprise has exactly ONE representant.
     * Enforced by UNIQUE constraint on representants.entreprise_id.
     */
    public function representant()
    {
        return $this->hasOne(Representant::class);
    }

    public function contrats()
    {
        return $this->hasMany(Contrat::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function courriers()
    {
        return $this->hasMany(Courrier::class);
    }

    public function factures()
    {
        return $this->hasMany(Facture::class);
    }

    // ── Audit trail hook ───────────────────────────────────

    /**
     * Writes an EntrepriseHistory snapshot before every update and delete.
     * See Contrat::booted() for the full explanation of this pattern.
     */
    protected static function booted(): void
    {
        static::updating(function (Entreprise $entreprise) {
            $changed = array_keys($entreprise->getDirty());
            if (empty($changed)) {
                return;
            }

            EntrepriseHistory::create([
                'entreprise_id' => $entreprise->id,
                'changed_by' => auth()->id(),
                'data' => $entreprise->getOriginal(),
                'changed_fields' => $changed,
                'action' => 'update',
            ]);
        });

        static::deleting(function (Entreprise $entreprise) {
            EntrepriseHistory::create([
                'entreprise_id' => $entreprise->id,
                'changed_by' => auth()->id(),
                'data' => $entreprise->getAttributes(),
                'changed_fields' => array_keys($entreprise->getAttributes()),
                'action' => 'delete',
            ]);
        });
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Whether the linked client user is currently allowed to log in.
     * Checked in AuthController::login() before issuing a token.
     */
    public function isActiveForClient(): bool
    {
        return $this->statut === 'actif';
    }
}