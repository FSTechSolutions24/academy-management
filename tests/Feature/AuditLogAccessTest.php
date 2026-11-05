<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_filter_only_their_organization_audit_history(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Owner', 'email' => 'owner@audit.test', 'academy_name' => 'Audit Academy', 'phone' => '+201000000000',
            'password' => 'Academy2026', 'password_confirmation' => 'Academy2026',
        ])->assertCreated();
        $owner = User::where('email', 'owner@audit.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $this->actingAs($owner->fresh());
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();

        AuditLog::create(['organization_id' => $organization->id, 'branch_id' => $branch->id, 'actor_id' => $owner->id, 'event' => 'staff.member_updated']);
        $foreign = Organization::create(['public_id' => (string) Str::ulid(), 'name_ar' => 'Foreign', 'slug' => 'foreign', 'subscription_status' => 'trial']);
        AuditLog::create(['organization_id' => $foreign->id, 'event' => 'foreign.secret_event']);

        $this->getJson('/api/audit-logs?event=staff.member_updated&branch='.$branch->public_id)
            ->assertOk()->assertJsonCount(1, 'logs')
            ->assertJsonPath('logs.0.event', 'staff.member_updated')
            ->assertJsonMissing(['event' => 'foreign.secret_event']);
    }

    public function test_teacher_cannot_view_organization_audit_history(): void
    {
        $organization = Organization::create(['public_id' => (string) Str::ulid(), 'name_ar' => 'Academy', 'slug' => 'academy', 'subscription_status' => 'trial']);
        $role = OrganizationRole::create(['organization_id' => $organization->id, 'key' => 'teacher', 'name' => 'Teacher', 'permissions' => ['view_students'], 'is_system' => true]);
        $teacher = User::factory()->create();
        $organization->users()->attach($teacher->id, ['role' => 'teacher', 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(), 'permissions' => json_encode([])]);

        $this->actingAs($teacher)->getJson('/api/audit-logs')->assertForbidden();
    }
}
