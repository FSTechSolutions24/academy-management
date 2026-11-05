<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupSchedule extends Model
{
    protected $fillable = ['organization_id', 'branch_id', 'teaching_group_id', 'day_of_week', 'starts_at', 'ends_at', 'room', 'mode', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }
}
