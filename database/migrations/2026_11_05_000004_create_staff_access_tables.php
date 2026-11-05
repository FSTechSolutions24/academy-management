<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 100);
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['organization_id','key']);
        });

        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('organization_roles')->nullOnDelete();
            $table->timestamp('joined_at')->nullable()->after('status');
        });

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('organization_roles')->cascadeOnDelete();
            $table->string('name',120); $table->string('email',190);
            $table->char('token_hash',64)->unique();
            $table->json('branch_ids');
            $table->timestamp('expires_at'); $table->timestamp('accepted_at')->nullable(); $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id','email']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event',120); $table->nullableMorphs('auditable');
            $table->json('before')->nullable(); $table->json('after')->nullable();
            $table->string('ip_address',45)->nullable(); $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['organization_id','event','created_at']);
        });

        $catalog = [
            'owner'=>['Owner',['*']],
            'organization_admin'=>['Organisation administrator',['manage_staff','manage_centres','manage_academic_structure','view_students','create_students','edit_students','archive_students','import_students','view_attendance','record_attendance','correct_attendance','create_examinations','enter_marks','publish_results','view_finance','record_payments','send_messages','manage_parent_links','view_reports','manage_settings']],
            'teacher'=>['Teacher',['view_students','create_students','edit_students','view_attendance','record_attendance','create_examinations','enter_marks','publish_results','view_reports']],
            'assistant'=>['Assistant',['view_students','create_students','view_attendance','record_attendance','enter_marks']],
            'receptionist'=>['Receptionist',['view_students','create_students','edit_students','view_attendance','record_attendance','record_payments']],
            'finance_officer'=>['Finance officer',['view_students','view_finance','record_payments','view_reports']],
        ];

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            $roleIds = [];
            foreach ($catalog as $key => [$name,$permissions]) {
                $roleIds[$key] = DB::table('organization_roles')->insertGetId([
                    'organization_id'=>$organizationId,'key'=>$key,'name'=>$name,'permissions'=>json_encode($permissions),
                    'is_system'=>true,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            DB::table('organization_memberships')->where('organization_id',$organizationId)->get()->each(function (object $membership) use ($roleIds): void {
                DB::table('organization_memberships')->where('id',$membership->id)->update([
                    'role_id'=>$roleIds[$membership->role] ?? $roleIds['assistant'],
                    'joined_at'=>$membership->created_at ?? now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('organization_invitations');
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id'); $table->dropColumn('joined_at');
        });
        Schema::dropIfExists('organization_roles');
    }
};
