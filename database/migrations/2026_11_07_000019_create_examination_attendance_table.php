<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('examination_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['present', 'late', 'excused', 'absent'])->default('present');
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            $table->unique(['examination_id', 'student_id']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('examination_attendance'); }
};
