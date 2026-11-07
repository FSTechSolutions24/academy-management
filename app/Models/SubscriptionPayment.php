<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = ['organization_id', 'initiated_by', 'subscription_plan_id', 'reference', 'status', 'billing_cycle', 'amount_minor', 'currency', 'gateway_order_id', 'gateway_intention_id', 'transaction_id', 'paid_at', 'gateway_metadata', 'note'];

    protected $casts = ['paid_at' => 'datetime', 'gateway_metadata' => 'array'];
}
