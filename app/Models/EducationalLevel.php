<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EducationalLevel extends Model { protected $fillable = ['organization_id','branch_id','name_ar','name_en','stage','sort_order','is_active']; protected function casts(): array { return ['is_active'=>'boolean']; } }
