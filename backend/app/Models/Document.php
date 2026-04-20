<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
        'document_type_id',
        'file_path',
        'date_expiration',
        'uploaded_by_user',
        'previous_version_id',
    ];

    protected $casts = [
        'date_expiration' => 'date',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user');
    }

    public function previousVersion()
    {
        return $this->belongsTo(Document::class, 'previous_version_id');
    }
}
