<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = ['name','is_required','has_expiration','description'];
    protected $casts = ['is_required' => 'boolean', 'has_expiration' => 'boolean'];

    public function documents() { return $this->hasMany(Document::class); }
}
