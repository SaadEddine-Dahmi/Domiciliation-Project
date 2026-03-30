<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Template extends Model
{
    use HasFactory;

    protected $fillable = ['domiciliataire_id','name','description'];

    public function domiciliataire() { return $this->belongsTo(User::class, 'domiciliataire_id'); }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'template_articles')
            ->withPivot('ordre')
            ->withTimestamps();
    }
}
