<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicYear extends Model { protected $fillable = ['organization_id','branch_id','name','starts_on','ends_on','status','is_active']; protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date','is_active'=>'boolean']; } }
