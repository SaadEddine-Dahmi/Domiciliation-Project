<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;
use App\Models\{Contrat, Representant};

class Entreprise extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'domiciliataire_id','client_user_id','raison_sociale','forme_juridique',
        'adresse','ville','pays','capital','date_creation','statut',
    ];

    protected $casts = [
        'date_creation' => 'date',
        'capital' => 'decimal:2',
    ];

    public function domiciliataire() { return $this->belongsTo(User::class, 'domiciliataire_id'); }
    public function clientUser() { return $this->belongsTo(User::class, 'client_user_id'); }

    public function representants() { return $this->hasMany(Representant::class); }
    public function contrats() { return $this->hasMany(Contrat::class); }
    public function documents() { return $this->hasMany(Document::class); }
    public function courriers() { return $this->hasMany(Courrier::class); }
    public function factures() { return $this->hasMany(Facture::class); }
}
