<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If a MySQL migration was interrupted after table creation, finish the
        // index safely instead of attempting to recreate the table.
        if (Schema::hasTable('attendance_corrections')) {
            Schema::table('attendance_corrections', function (Blueprint $table): void {
                $table->index(['branch_id', 'attendance_session_id', 'created_at'], 'attendance_corrections_scope_idx');
            });
            return;
        }
        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('method', 20)->default('manual');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'attendance_session_id', 'created_at'], 'attendance_corrections_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
