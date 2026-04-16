<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntrepriseHistory extends Model
{
    protected $table = 'entreprises_history';

    protected $fillable = [
        'entreprise_id',
        'changed_by',     // user who made the change
        'data',           // full snapshot of old record
        'changed_fields', // array of field names that changed
        'action',         // 'update' or 'delete'
    ];

    protected $casts = [
        'data'           => 'array',
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