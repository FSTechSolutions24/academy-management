<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price_minor')->default(0);
            $table->unsignedBigInteger('yearly_price_minor')->nullable();
            $table->unsignedInteger('centre_limit')->nullable();
            $table->unsignedInteger('student_limit')->nullable();
            $table->unsignedInteger('staff_limit')->nullable();
            $table->unsignedInteger('group_limit')->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('organization_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->string('billing_cycle', 20)->default('trial');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['organization_id', 'status', 'ends_at']);
        });
        Schema::create('platform_payment_gateway_settings', function (Blueprint $table): void {
            $table->id();
            $table->text('secret_key')->nullable();
            $table->text('public_key')->nullable();
            $table->text('hmac_secret')->nullable();
            $table->text('payment_methods')->nullable();
            $table->string('iframe_id', 100)->nullable();
            $table->string('api_base')->default('https://accept.paymob.com');
            $table->string('checkout_base')->default('https://accept.paymob.com/unifiedcheckout');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        $now = now();
        DB::table('subscription_plans')->insert([
            ['slug' => 'starter', 'name' => 'Starter', 'description' => 'For an independent teacher getting organised.', 'monthly_price_minor' => 39900, 'yearly_price_minor' => 399000, 'centre_limit' => 1, 'student_limit' => 250, 'staff_limit' => 2, 'group_limit' => 15, 'currency' => 'EGP', 'features' => json_encode(['Student records and guardians', 'QR/manual attendance', 'Basic parent updates']), 'is_active' => true, 'is_featured' => false, 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'growth', 'name' => 'Growth', 'description' => 'For a busy centre with a growing teaching team.', 'monthly_price_minor' => 79900, 'yearly_price_minor' => 799000, 'centre_limit' => 3, 'student_limit' => 1500, 'staff_limit' => 12, 'group_limit' => 80, 'currency' => 'EGP', 'features' => json_encode(['Everything in Starter', 'Groups and schedules', 'Fees and receipts', 'Parent portal']), 'is_active' => true, 'is_featured' => true, 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'academy', 'name' => 'Academy', 'description' => 'Multi-centre operations with advanced reporting.', 'monthly_price_minor' => 159900, 'yearly_price_minor' => 1599000, 'centre_limit' => 10, 'student_limit' => 10000, 'staff_limit' => 50, 'group_limit' => 500, 'currency' => 'EGP', 'features' => json_encode(['Everything in Growth', 'Multi-centre reporting', 'Offline attendance queue', 'Priority support']), 'is_active' => true, 'is_featured' => false, 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_gateway_settings');
        Schema::dropIfExists('organization_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
