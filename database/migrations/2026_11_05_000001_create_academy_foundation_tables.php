<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id(); $table->ulid('public_id')->unique(); $table->string('name_ar'); $table->string('name_en')->nullable(); $table->string('slug')->unique();
            $table->string('email')->nullable(); $table->string('contact_phone',24)->nullable(); $table->string('whatsapp_phone',24)->nullable();
            $table->string('governorate')->nullable(); $table->string('city')->nullable(); $table->string('preferred_locale',5)->default('ar');
            $table->string('currency',3)->default('EGP'); $table->string('timezone')->default('Africa/Cairo'); $table->string('subscription_status')->default('trial');
            $table->timestamp('trial_ends_at')->nullable(); $table->json('settings')->nullable(); $table->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('assistant'); $table->string('status')->default('active'); $table->json('permissions')->nullable(); $table->timestamps();
            $table->unique(['organization_id','user_id']); $table->index(['user_id','status']);
        });
        Schema::create('branches', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name_ar'); $table->string('name_en')->nullable(); $table->text('address')->nullable(); $table->string('governorate')->nullable(); $table->string('city')->nullable(); $table->string('phone',24)->nullable(); $table->boolean('is_active')->default(true); $table->timestamps(); $table->index(['organization_id','is_active']); });
        Schema::create('academic_years', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->date('starts_on'); $table->date('ends_on'); $table->string('status')->default('upcoming'); $table->boolean('is_active')->default(false); $table->timestamps(); $table->unique(['organization_id','name']); });
        Schema::create('educational_levels', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name_ar'); $table->string('name_en')->nullable(); $table->string('stage')->nullable(); $table->unsignedSmallInteger('sort_order')->default(0); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['organization_id','name_ar']); });
        Schema::create('subjects', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name_ar'); $table->string('name_en')->nullable(); $table->string('code',24); $table->string('color',16)->default('#6d5dfc'); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['organization_id','code']); });
    }
    public function down(): void { Schema::dropIfExists('subjects'); Schema::dropIfExists('educational_levels'); Schema::dropIfExists('academic_years'); Schema::dropIfExists('branches'); Schema::dropIfExists('organization_memberships'); Schema::dropIfExists('organizations'); }
};
