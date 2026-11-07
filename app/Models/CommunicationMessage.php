<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }

    public function guardian(): BelongsTo { return $this->belongsTo(Guardian::class); }
}
