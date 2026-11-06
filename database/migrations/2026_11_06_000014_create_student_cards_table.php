<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_cards', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->default('active');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'branch_id', 'student_id', 'status'], 'student_cards_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cards');
    }
};
