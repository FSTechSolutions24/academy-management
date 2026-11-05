<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRole;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private function registerOwner(): Organization
    {
        $this->postJson('/api/register', [
            'name' => 'Mariam Beshoy', 'email' => 'owner@academy.test', 'academy_name' => 'Future Leaders',
            'phone' => '+201000000000', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026',
        ])->assertCreated();
        $owner = User::where('email', 'owner@academy.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $this->actingAs($owner->fresh());

        return Organization::firstOrFail();
    }

    public function test_new_staff_member_accepts_hashed_invitation_for_assigned_centres(): void
    {
        Mail::fake();
        $organization = $this->registerOwner();
        $branch = $organization->branches()->firstOrFail();
        $role = OrganizationRole::where('organization_id', $organization->id)->where('key', 'assistant')->firstOrFail();

        $this->postJson('/api/team/invitations', [
            'name' => 'Nour Ahmed', 'email' => 'nour@example.test', 'role_id' => $role->id, 'branches' => [$branch->public_id],
        ])->assertCreated()->assertJsonPath('invitation.status', 'pending');

        $plainToken = null;
        Mail::assertSent(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$plainToken): bool {
            parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $query);
            $plainToken = $query['token'] ?? null;

            return $plainToken !== null;
        });
        $this->assertNotNull($plainToken);
        $this->assertDatabaseMissing('organization_invitations', ['token_hash' => $plainToken]);

        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/invitations/accept?token='.urlencode($plainToken))->assertOk()->assertJsonPath('invitation.role.key', 'assistant');
        $this->postJson('/api/invitations/accept', [
            'token' => $plainToken, 'name' => 'Nour Ahmed', 'password' => 'Assistant2026', 'password_confirmation' => 'Assistant2026',
        ])->assertOk();

        $user = User::where('email', 'nour@example.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'assistant', 'status' => 'active']);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $branch->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'staff.invitation_created']);
        $this->getJson('/api/invitations/accept?token='.urlencode($plainToken))->assertNotFound();
    }

    public function test_owner_can_assign_existing_user_and_change_their_role_and_centres(): void
    {
        Mail::fake();
        $organization = $this->registerOwner();
        $main = $organization->branches()->firstOrFail();
        $second = $this->postJson('/api/branches', ['name_ar' => 'مركز المعادي', 'name_en' => 'Maadi Centre'])->assertCreated()->json('branch');
        $teacher = OrganizationRole::where('organization_id', $organization->id)->where('key', 'teacher')->firstOrFail();
        $assistant = OrganizationRole::where('organization_id', $organization->id)->where('key', 'assistant')->firstOrFail();
        $existing = User::factory()->create(['email' => 'existing@example.test']);

        $member = $this->postJson('/api/team/invitations', [
            'name' => $existing->name, 'email' => $existing->email, 'role_id' => $teacher->id, 'branches' => [$main->public_id, $second['public_id']],
        ])->assertCreated()->json('member');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('branch_user', 2);

        $this->patchJson('/api/team/members/'.$member['id'], [
            'role_id' => $assistant->id, 'status' => 'active', 'branches' => [$second['public_id']],
            'permissions_allow' => ['view_finance'], 'permissions_deny' => ['create_students'],
        ])->assertOk()->assertJsonPath('member.role.key', 'assistant')
            ->assertJsonPath('member.permission_overrides.allow.0', 'view_finance')
            ->assertJsonPath('member.permission_overrides.deny.0', 'create_students');
        $this->assertDatabaseCount('branch_user', 1);
        $this->assertDatabaseHas('branch_user', ['user_id' => $existing->id, 'branch_id' => $organization->branches()->where('public_id', $second['public_id'])->value('id')]);

        $membership = OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $existing->id)->with('roleDefinition')->firstOrFail();
        $permissions = app(PermissionService::class);
        $this->assertTrue($permissions->has($membership, 'view_finance'));
        $this->assertFalse($permissions->has($membership, 'create_students'));

        $this->actingAs($existing)->getJson('/api/team')->assertForbidden();
    }

    public function test_owner_can_create_custom_role_but_cannot_assign_foreign_centre(): void
    {
        $organization = $this->registerOwner();
        $role = $this->postJson('/api/team/roles', ['name' => 'Exam coordinator', 'permissions' => ['view_students', 'create_examinations', 'enter_marks']])
            ->assertCreated()->json('role');
        $this->assertFalse($role['is_system']);

        $foreign = Organization::create(['public_id' => '01JSTAFF000000000000000001', 'name_ar' => 'Other', 'slug' => 'other', 'preferred_locale' => 'ar', 'currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'subscription_status' => 'trial']);
        $foreignBranch = $foreign->branches()->create(['public_id' => '01JSTAFF000000000000000002', 'name_ar' => 'فرع آخر', 'name_en' => 'Other']);
        $this->postJson('/api/team/invitations', [
            'name' => 'Intruder', 'email' => 'intruder@example.test', 'role_id' => $role['id'], 'branches' => [$foreignBranch->public_id],
        ])->assertUnprocessable();
    }
}
