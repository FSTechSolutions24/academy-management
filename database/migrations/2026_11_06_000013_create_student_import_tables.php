<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_name', 255);
            $table->string('storage_path', 500)->nullable();
            $table->string('status', 30)->default('preview');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'branch_id', 'status']);
        });

        Schema::create('student_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_import_id')->constrained('student_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->json('errors')->nullable();
            $table->string('status', 20)->default('ready');
            $table->timestamps();
            $table->unique(['student_import_id', 'row_number']);
            $table->index(['student_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_import_rows');
        Schema::dropIfExists('student_imports');
    }
};
