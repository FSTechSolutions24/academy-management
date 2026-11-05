<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_registers_and_searches_a_student_inside_assigned_centre(): void
    {
        $this->postJson('/api/register', ['name' => 'Owner', 'email' => 'owner@student.test', 'academy_name' => 'Academy', 'phone' => '+201000000000', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $owner = User::where('email', 'owner@student.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $teacher = User::factory()->create();
        $role = OrganizationRole::where('organization_id', $organization->id)->where('key', 'teacher')->firstOrFail();
        $organization->users()->attach($teacher->id, ['role' => 'teacher', 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(), 'permissions' => json_encode([])]);
        $branch->users()->attach($teacher->id, ['organization_id' => $organization->id, 'role' => 'teacher', 'status' => 'active']);

        $this->actingAs($teacher)->postJson('/api/students', [
            'name_ar' => 'مريم أحمد', 'name_en' => 'Mariam Ahmed', 'guardian_name' => 'Ahmed Hassan', 'guardian_phone' => '+201111111111', 'guardian_relationship' => 'father',
            'academic_year_id' => $branch->academicYears()->firstOrFail()->id, 'educational_level_id' => $branch->educationalLevels()->firstOrFail()->id,
        ])->assertCreated()->assertJsonPath('student.student_code', 'STU-000001')->assertJsonPath('student.guardian.name', 'Ahmed Hassan');

        $this->getJson('/api/students?search=Mariam')->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('pagination.total', 1);
        $this->assertDatabaseHas('enrollments', ['branch_id' => $branch->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'branch_id' => $branch->id, 'event' => 'student.created']);
    }

    public function test_student_directory_never_leaks_a_foreign_organization_student(): void
    {
        $foreign = Organization::create(['public_id' => (string) Str::ulid(), 'name_ar' => 'Foreign', 'slug' => 'foreign-students', 'subscription_status' => 'trial']);
        $foreignBranch = $foreign->branches()->create(['public_id' => (string) Str::ulid(), 'name_ar' => 'Foreign branch']);
        $foreignBranch->students()->create(['public_id' => (string) Str::ulid(), 'organization_id' => $foreign->id, 'student_code' => 'STU-000001', 'name_ar' => 'Secret Student']);

        $this->actingAs(User::factory()->create())->getJson('/api/students')->assertForbidden();
    }
}
