<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntrepriseHistory extends Model
{
    protected $table = 'entreprises_history';

    protected $fillable = [
        'entreprise_id',
        'changed_by',
        'data',
        'changed_fields',
        'action',
    ];

    protected $casts = [
        'data' => 'array',
        'changed_fields' => 'array',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}