<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepresentantHistory extends Model
{
    protected $table = 'representants_history';

    protected $fillable = [
        'representant_id',
        'domiciliataire_id', // denormalised tenant owner, for scoped queries
        'changed_by',
        'data',
        'changed_fields',
        'action',
    ];

    protected $casts = [
        'data' => 'array',
        'changed_fields' => 'array',
    ];

    // The representant this snapshot was taken from.
    public function representant()
    {
        return $this->belongsTo(Representant::class);
    }

    // The user who made the change captured in this snapshot.
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}