<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Document extends Model {
  use HasUuids;
  protected $keyType = 'string';
  public $incrementing = false;
  protected $fillable = ['id','client_id','type','name','file_path','status','uploaded_at'];
  protected $casts = ['uploaded_at' => 'datetime'];

  // Document belongs to client
  public function client() { return $this->belongsTo(Client::class); }
}