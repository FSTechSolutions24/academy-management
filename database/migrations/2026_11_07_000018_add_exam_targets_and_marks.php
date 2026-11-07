<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('examination_groups', function(Blueprint $table): void { $table->id(); $table->foreignId('examination_id')->constrained()->cascadeOnDelete(); $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete(); $table->timestamps(); $table->unique(['examination_id','teaching_group_id']); }); Schema::create('examination_marks', function(Blueprint $table): void { $table->id(); $table->foreignId('examination_id')->constrained()->cascadeOnDelete(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete(); $table->decimal('mark',8,2)->nullable(); $table->text('comment')->nullable(); $table->timestamps(); $table->unique(['examination_id','student_id']); }); }
 public function down(): void { Schema::dropIfExists('examination_marks'); Schema::dropIfExists('examination_groups'); }
};
