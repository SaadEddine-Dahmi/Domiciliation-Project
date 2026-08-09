<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContratHistory extends Model
{
    protected $table = 'contrats_history';

    protected $fillable = [
        'contrat_id',
        'changed_by',
        'data',
        'changed_fields',
        'action',
    ];

    protected $casts = [
        'data'           => 'array',
        'changed_fields' => 'array',
    ];

    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}