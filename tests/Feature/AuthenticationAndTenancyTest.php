<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationAndTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function verifyRegisteredUser(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $user->markEmailAsVerified();
        $this->actingAs($user->fresh());
    }

    public function test_owner_registration_creates_an_isolated_academy_foundation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Mariam Beshoy', 'email' => 'mariam@example.test', 'academy_name' => 'Future Leaders',
            'phone' => '+201000000000', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026',
        ]);

        $response->assertCreated()->assertJsonPath('organization.name_ar', 'Future Leaders');
        $this->assertAuthenticated();
        $organization = Organization::firstOrFail();
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $this->assertSame(1, $organization->branches()->count());
        $this->assertSame(3, $organization->subjects()->count());
        $branch = $organization->branches()->firstOrFail();
        $this->assertTrue($branch->is_head_office);
        $this->assertSame($branch->id, $organization->subjects()->firstOrFail()->branch_id);
    }

    public function test_dashboard_uses_only_the_authenticated_membership_organization(): void
    {
        $first = Organization::create(['public_id' => '01JTEST0000000000000000001', 'name_ar' => 'First Academy', 'slug' => 'first', 'preferred_locale' => 'ar', 'currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'subscription_status' => 'trial']);
        $second = Organization::create(['public_id' => '01JTEST0000000000000000002', 'name_ar' => 'Secret Academy', 'slug' => 'secret', 'preferred_locale' => 'ar', 'currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'subscription_status' => 'trial']);
        $user = User::factory()->create();
        $first->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);
        $firstBranch = $first->branches()->create(['public_id' => (string) Str::ulid(), 'name_ar' => 'المركز الأول', 'name_en' => 'First centre', 'is_head_office' => true]);
        $secondBranch = $second->branches()->create(['public_id' => (string) Str::ulid(), 'name_ar' => 'المركز السري', 'name_en' => 'Secret centre', 'is_head_office' => true]);
        $second->subjects()->create(['branch_id' => $secondBranch->id, 'name_ar' => 'سر', 'name_en' => 'Secret subject', 'code' => 'SECRET']);
        $first->subjects()->create(['branch_id' => $firstBranch->id, 'name_ar' => 'فيزياء', 'name_en' => 'Physics', 'code' => 'PHY']);

        $this->actingAs($user)->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('organization.name_ar', 'First Academy')
            ->assertJsonPath('branch.name_en', 'First centre')
            ->assertJsonFragment(['code' => 'PHY'])
            ->assertJsonMissing(['code' => 'SECRET']);
    }

    public function test_owner_can_create_and_switch_to_an_independent_centre(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mariam Beshoy', 'email' => 'owner@example.test', 'academy_name' => 'Future Leaders',
            'phone' => '+201000000000', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026',
        ])->assertCreated();
        $this->verifyRegisteredUser('owner@example.test');

        $created = $this->postJson('/api/branches', [
            'name_ar' => 'مركز مدينة نصر', 'name_en' => 'Nasr City Centre', 'governorate' => 'Cairo', 'city' => 'Nasr City',
        ])->assertCreated()->json('branch');

        $this->postJson("/api/branches/{$created['public_id']}/select")->assertOk();
        $createdBranchId = Organization::firstOrFail()->branches()->where('public_id', $created['public_id'])->value('id');
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.created', 'branch_id' => $createdBranchId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.selected', 'branch_id' => $createdBranchId]);
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('branch.name_en', 'Nasr City Centre')
            ->assertJsonPath('setup.1.done', false)
            ->assertJsonCount(0, 'subjects');
    }

    public function test_staff_member_cannot_select_an_unassigned_centre(): void
    {
        $organization = Organization::create(['public_id' => '01JTEST0000000000000000003', 'name_ar' => 'Academy', 'slug' => 'academy', 'preferred_locale' => 'ar', 'currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'subscription_status' => 'trial']);
        $assigned = $organization->branches()->create(['public_id' => (string) Str::ulid(), 'name_ar' => 'مسموح', 'name_en' => 'Assigned']);
        $blocked = $organization->branches()->create(['public_id' => (string) Str::ulid(), 'name_ar' => 'غير مسموح', 'name_en' => 'Blocked']);
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'assistant', 'status' => 'active']);
        $assigned->users()->attach($user->id, ['organization_id' => $organization->id, 'role' => 'assistant', 'status' => 'active']);

        $this->actingAs($user)->postJson("/api/branches/{$blocked->public_id}/select")->assertNotFound();
        $this->actingAs($user)->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('branch.name_en', 'Assigned')
            ->assertJsonCount(1, 'branches');
        $this->actingAs($user)->postJson('/api/academic-structure/subjects', [
            'name_ar' => 'غير مصرح', 'name_en' => 'Forbidden', 'code' => 'NOPE', 'color' => '#000000',
        ])->assertForbidden();
    }

    public function test_academic_structure_is_created_only_inside_the_active_centre(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mariam Beshoy', 'email' => 'structure@example.test', 'academy_name' => 'Future Leaders',
            'phone' => '+201000000000', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026',
        ])->assertCreated();
        $this->verifyRegisteredUser('structure@example.test');
        $mainBranch = Organization::firstOrFail()->branches()->firstOrFail();
        $newBranch = $this->postJson('/api/branches', ['name_ar' => 'مركز مستقل', 'name_en' => 'Independent Centre'])
            ->assertCreated()->json('branch');
        $this->postJson("/api/branches/{$newBranch['public_id']}/select")->assertOk();

        $this->postJson('/api/academic-structure/years', [
            'name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30', 'status' => 'active', 'is_active' => true,
        ])->assertCreated();
        $this->postJson('/api/academic-structure/levels', [
            'name_ar' => 'الصف الأول الإعدادي', 'name_en' => 'Preparatory 1', 'stage' => 'preparatory', 'sort_order' => 1,
        ])->assertCreated();
        $this->postJson('/api/academic-structure/subjects', [
            'name_ar' => 'الكيمياء', 'name_en' => 'Chemistry', 'code' => 'CHEM', 'color' => '#ef476f',
        ])->assertCreated();
        $independentBranchId = Organization::firstOrFail()->branches()->where('public_id', $newBranch['public_id'])->value('id');
        $this->assertDatabaseHas('audit_logs', ['event' => 'academic_year.created', 'branch_id' => $independentBranchId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'educational_level.created', 'branch_id' => $independentBranchId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'subject.created', 'branch_id' => $independentBranchId]);

        $this->getJson('/api/academic-structure')->assertOk()
            ->assertJsonPath('branch.name_en', 'Independent Centre')
            ->assertJsonFragment(['code' => 'CHEM'])
            ->assertJsonCount(1, 'academic_years');

        $this->postJson("/api/branches/{$mainBranch->public_id}/select")->assertOk();
        $this->getJson('/api/academic-structure')->assertOk()
            ->assertJsonMissing(['code' => 'CHEM'])
            ->assertJsonFragment(['code' => 'PHY']);
    }

    public function test_user_without_active_membership_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/dashboard')->assertForbidden();
    }
}
