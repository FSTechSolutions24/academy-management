<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingGroup extends Model
{
    protected $fillable = ['organization_id', 'branch_id', 'academic_year_id', 'educational_level_id', 'subject_id', 'public_id', 'name_ar', 'name_en', 'code', 'max_capacity', 'status', 'notes'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function educationalLevel(): BelongsTo
    {
        return $this->belongsTo(EducationalLevel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_enrollments')->withPivot(['status', 'enrolled_on', 'ended_on'])->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(GroupSchedule::class);
    }
}
