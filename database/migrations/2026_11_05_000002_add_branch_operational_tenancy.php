<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique()->after('id');
            $table->boolean('is_head_office')->default(false)->after('phone');
            $table->json('settings')->nullable()->after('is_active');
        });

        DB::table('branches')->orderBy('id')->get()->each(function (object $branch): void {
            DB::table('branches')->where('id', $branch->id)->update([
                'public_id' => (string) Str::ulid(),
            ]);
        });

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
            $table->index(['organization_id', 'user_id', 'status']);
        });

        Schema::table('academic_years', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
            $table->index('organization_id');
            $table->dropUnique(['organization_id', 'name']);
            $table->unique(['organization_id', 'branch_id', 'name']);
            $table->index(['organization_id', 'branch_id', 'is_active']);
        });
        Schema::table('educational_levels', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
            $table->index('organization_id');
            $table->dropUnique(['organization_id', 'name_ar']);
            $table->unique(['organization_id', 'branch_id', 'name_ar']);
            $table->index(['organization_id', 'branch_id', 'is_active']);
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
            $table->index('organization_id');
            $table->dropUnique(['organization_id', 'code']);
            $table->unique(['organization_id', 'branch_id', 'code']);
            $table->index(['organization_id', 'branch_id', 'is_active']);
        });

        DB::table('organizations')->orderBy('id')->get()->each(function (object $organization): void {
            $branchId = DB::table('branches')->where('organization_id', $organization->id)->orderBy('id')->value('id');
            if (! $branchId) {
                return;
            }
            DB::table('branches')->where('id', $branchId)->update(['is_head_office' => true]);
            foreach (['academic_years', 'educational_levels', 'subjects'] as $table) {
                DB::table($table)->where('organization_id', $organization->id)->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'branch_id', 'code']);
            $table->dropIndex(['organization_id', 'branch_id', 'is_active']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropIndex(['organization_id']);
            $table->unique(['organization_id', 'code']);
        });
        Schema::table('educational_levels', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'branch_id', 'name_ar']);
            $table->dropIndex(['organization_id', 'branch_id', 'is_active']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropIndex(['organization_id']);
            $table->unique(['organization_id', 'name_ar']);
        });
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'branch_id', 'name']);
            $table->dropIndex(['organization_id', 'branch_id', 'is_active']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropIndex(['organization_id']);
            $table->unique(['organization_id', 'name']);
        });
        Schema::dropIfExists('branch_user');
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['public_id', 'is_head_office', 'settings']);
        });
    }
};
