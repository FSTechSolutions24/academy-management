<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_opted_in')->default(false);
            $table->string('method', 24);
            $table->string('notice_version', 40)->default('whatsapp-v1');
            $table->text('notes')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['organization_id', 'branch_id', 'student_id', 'guardian_id'], 'communication_consent_unique');
            $table->index(['branch_id', 'is_opted_in']);
        });

        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('provider_template_name', 100);
            $table->string('language', 12)->default('en');
            $table->string('category', 32)->default('utility');
            $table->json('variables')->nullable();
            $table->text('body_preview')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'provider_template_name']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('communication_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24)->default('whatsapp');
            $table->string('to_phone', 24);
            $table->string('template_name', 100);
            $table->string('language', 12)->default('en');
            $table->json('parameters')->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id', 160)->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('communication_consents');
    }
};
