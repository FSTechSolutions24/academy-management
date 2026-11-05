<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('educational_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('public_id', 26)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            $table->string('code', 40);
            $table->unsignedInteger('max_capacity')->nullable();
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('group_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'completed', 'withdrawn'])->default('active');
            $table->date('enrolled_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->unique(['teaching_group_id', 'student_id']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_enrollments');
        Schema::dropIfExists('teaching_groups');
    }
};
