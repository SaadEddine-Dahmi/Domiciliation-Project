<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepresentantHistory extends Model
{
    protected $table = 'representants_history';

    protected $fillable = [
        'representant_id',
        'changed_by',     // user who made the change
        'data',           // full snapshot of old record
        'changed_fields', // array of field names that changed
        'action',         // 'update' or 'delete'
    ];

    protected $casts = [
        'data'           => 'array',
        'changed_fields' => 'array',
    ];

    public function representant()
    {
        return $this->belongsTo(Representant::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
