<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ExaminationMark extends Model { protected $fillable=['examination_id','student_id','entered_by','mark','comment']; protected $casts=['mark'=>'decimal:2']; public function student(): BelongsTo{return $this->belongsTo(Student::class);} public function examination(): BelongsTo{return $this->belongsTo(Examination::class);} }
