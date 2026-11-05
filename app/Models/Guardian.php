<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    protected $fillable = ['organization_id', 'name', 'phone', 'whatsapp_phone', 'email'];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }
}
