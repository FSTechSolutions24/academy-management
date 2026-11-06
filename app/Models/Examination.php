<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Examination extends Model { protected $fillable=['organization_id','branch_id','subject_id','created_by','public_id','title','exam_date','total_marks','status','notes']; protected $casts=['exam_date'=>'date']; public function subject(): BelongsTo{return $this->belongsTo(Subject::class);} }
