<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom', 'prenom', 'email', 'password', 'telephone', 'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    // Tenant-owned resources
    public function entreprises()
    {
        return $this->hasMany(Entreprise::class, 'domiciliataire_id');
    }

    public function contrats()
    {
        return $this->hasMany(Contrat::class, 'domiciliataire_id');
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'domiciliataire_id');
    }

    public function uploadedDocuments()
    {
        return $this->hasMany(Document::class, 'uploaded_by_user');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class, 'user_id');
    }
}
