<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = ['organization_id', 'branch_id', 'teaching_group_id', 'created_by', 'session_date', 'starts_at', 'ends_at', 'status', 'closed_at'];

    protected $casts = ['session_date' => 'date', 'closed_at' => 'datetime'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
