<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['organization_id', 'public_id', 'name_ar', 'name_en', 'address', 'governorate', 'city', 'phone', 'is_head_office', 'is_active', 'settings'];

    protected function casts(): array
    {
        return ['is_head_office' => 'boolean', 'is_active' => 'boolean', 'settings' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withPivot(['organization_id', 'role', 'status'])->withTimestamps();
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function educationalLevels(): HasMany
    {
        return $this->hasMany(EducationalLevel::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachingGroups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }
}
