<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FoundItem extends Model {
 protected $fillable=['category','public_description','found_at','returned_at','return_reference'];
 protected function casts(): array { return ['found_at'=>'date:Y-m-d','returned_at'=>'datetime']; }
}
