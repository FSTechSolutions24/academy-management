<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationAttendance extends Model
{
    protected $table = 'examination_attendance';
    protected $fillable = ['organization_id', 'branch_id', 'examination_id', 'student_id', 'recorded_by', 'status', 'recorded_at'];
    protected $casts = ['recorded_at' => 'datetime'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function examination(): BelongsTo { return $this->belongsTo(Examination::class); }
}
