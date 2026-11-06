<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCard extends Model
{
    protected $fillable = ['public_id', 'organization_id', 'branch_id', 'student_id', 'token_hash', 'status', 'issued_by', 'issued_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
