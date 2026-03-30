<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Client extends Model {
  use HasUuids;
  protected $keyType = 'string';
  public $incrementing = false;
  protected $fillable = ['id','user_id','company_name','manager_name','phone','status'];

  // Client has many docs
  public function documents() { return $this->hasMany(Document::class); }
}