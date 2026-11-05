<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentAccessLink extends Model
{
    protected $fillable = ['organization_id', 'branch_id', 'student_id', 'created_by', 'token_hash', 'label', 'expires_at', 'revoked_at', 'last_used_at'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
