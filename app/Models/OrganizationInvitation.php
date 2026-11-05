<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationInvitation extends Model
{
    protected $fillable=['organization_id','invited_by','role_id','name','email','token_hash','branch_ids','expires_at','accepted_at','revoked_at'];
    protected function casts(): array { return ['branch_ids'=>'array','expires_at'=>'datetime','accepted_at'=>'datetime','revoked_at'=>'datetime']; }
    public function organization(){return $this->belongsTo(Organization::class);}
    public function inviter(){return $this->belongsTo(User::class,'invited_by');}
    public function role(){return $this->belongsTo(OrganizationRole::class,'role_id');}
}
