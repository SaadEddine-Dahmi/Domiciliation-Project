<?php
// app/Models/DomiciliataireProfile.php
// Stores company identity fields for a domiciliataire account.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomiciliataireProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_societe',
        'contract_title',
        'rc',
        'if_fiscal',
        'tp',
        'adresses',
    ];

    protected $casts = [
        'adresses' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAdressesListAttribute(): array
    {
        return is_array($this->adresses) ? $this->adresses : [];
    }
}
