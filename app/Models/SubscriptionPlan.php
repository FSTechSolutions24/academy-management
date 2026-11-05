<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'monthly_price_minor', 'yearly_price_minor', 'centre_limit', 'student_limit', 'staff_limit', 'group_limit', 'currency', 'features', 'is_active', 'is_featured', 'position'];

    protected $casts = ['features' => 'array', 'is_active' => 'boolean', 'is_featured' => 'boolean'];
}
