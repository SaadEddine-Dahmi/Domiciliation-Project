<?php
// app/Models/Entreprise.php
// Represents a client company owned by a domiciliataire tenant.

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasRepresentant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model
{
    use HasFactory, BelongsToTenant, HasRepresentant;

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
        'statut',
    ];

    protected $casts = [
        'date_creation' => 'date',
        'capital' => 'decimal:2',
    ];

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

    // Records compact history snapshots for account history exports.
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

    public function isActiveForClient(): bool
    {
        return $this->statut === 'actif';
    }
}
