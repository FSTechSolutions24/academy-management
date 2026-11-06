<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('examinations', function(Blueprint $table): void { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('branch_id')->constrained()->cascadeOnDelete(); $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('public_id',26)->unique(); $table->string('title'); $table->date('exam_date'); $table->unsignedInteger('total_marks')->default(100); $table->enum('status',['draft','published','locked'])->default('draft'); $table->text('notes')->nullable(); $table->timestamps(); $table->index(['branch_id','exam_date','status']); }); }
 public function down(): void { Schema::dropIfExists('examinations'); }
};
