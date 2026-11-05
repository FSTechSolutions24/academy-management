<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Subject extends Model { protected $fillable = ['organization_id','branch_id','name_ar','name_en','code','color','is_active']; protected function casts(): array { return ['is_active'=>'boolean']; } }
