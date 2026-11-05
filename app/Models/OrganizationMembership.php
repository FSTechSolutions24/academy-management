<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;
class OrganizationMembership extends Pivot
{
    protected $table='organization_memberships'; public $incrementing=true;
    protected $fillable=['organization_id','user_id','role','role_id','status','joined_at','permissions'];
    protected function casts(): array { return ['permissions'=>'array','joined_at'=>'datetime']; }
    public function user(){return $this->belongsTo(User::class);}
    public function roleDefinition(){return $this->belongsTo(OrganizationRole::class,'role_id');}
}
