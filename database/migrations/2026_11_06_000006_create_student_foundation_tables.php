<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_sequences', function (Blueprint $table): void {
            $table->foreignId('branch_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('student_code', 30);
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone', 24)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'student_code']);
            $table->index(['organization_id', 'branch_id', 'status']);
            $table->index(['branch_id', 'name_ar']);
        });
        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('phone', 24);
            $table->string('whatsapp_phone', 24)->nullable();
            $table->string('email', 190)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'phone']);
        });
        Schema::create('student_guardians', function (Blueprint $table): void {
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 40)->default('guardian');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->primary(['guardian_id', 'student_id']);
        });
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('educational_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->date('enrolled_on');
            $table->timestamps();
            $table->index(['branch_id', 'academic_year_id', 'educational_level_id', 'status'], 'enrollments_branch_academic_level_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
        Schema::dropIfExists('student_sequences');
    }
};
