<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationRole extends Model
{
    protected $fillable=['organization_id','key','name','permissions','is_system'];
    protected function casts(): array { return ['permissions'=>'array','is_system'=>'boolean']; }
}
