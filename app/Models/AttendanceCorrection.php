<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'branch_id', 'attendance_session_id', 'attendance_record_id', 'student_id', 'changed_by', 'from_status', 'to_status', 'method', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
