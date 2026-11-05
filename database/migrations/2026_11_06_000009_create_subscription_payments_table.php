<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('status', 20)->default('pending');
            $table->string('billing_cycle', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('EGP');
            $table->string('gateway_order_id', 80)->nullable()->unique();
            $table->string('gateway_intention_id', 100)->nullable();
            $table->string('transaction_id', 80)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('subscription_payments'); }
};
