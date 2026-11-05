<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\OrganizationMembership;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'name_ar', 'name_en', 'slug', 'email', 'contact_phone',
        'whatsapp_phone', 'governorate', 'city', 'preferred_locale', 'currency',
        'timezone', 'subscription_status', 'trial_ends_at', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array', 'trial_ends_at' => 'datetime'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->using(OrganizationMembership::class)
            ->withPivot(['id','role','role_id','status','joined_at','permissions'])->withTimestamps();
    }

    public function academicYears(): HasMany { return $this->hasMany(AcademicYear::class); }
    public function educationalLevels(): HasMany { return $this->hasMany(EducationalLevel::class); }
    public function subjects(): HasMany { return $this->hasMany(Subject::class); }
    public function branches(): HasMany { return $this->hasMany(Branch::class); }
    public function roles(): HasMany { return $this->hasMany(OrganizationRole::class); }
    public function invitations(): HasMany { return $this->hasMany(OrganizationInvitation::class); }
}
