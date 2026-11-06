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

    public function test_teacher_can_edit_and_archive_a_student_with_audit_trail(): void
    {
        $this->postJson('/api/register', ['name' => 'Owner', 'email' => 'edit-owner@student.test', 'academy_name' => 'Academy', 'phone' => '+201000000001', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $owner = User::where('email', 'edit-owner@student.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $teacher = User::factory()->create();
        $role = OrganizationRole::where('organization_id', $organization->id)->where('key', 'teacher')->firstOrFail();
        $organization->users()->attach($teacher->id, ['role' => 'teacher', 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(), 'permissions' => json_encode([])]);
        $branch->users()->attach($teacher->id, ['organization_id' => $organization->id, 'role' => 'teacher', 'status' => 'active']);
        $student = $branch->students()->create(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'student_code' => 'STU-000001', 'name_ar' => 'طالب', 'name_en' => 'Student', 'status' => 'active']);

        $this->actingAs($teacher)->patchJson('/api/students/'.$student->public_id, ['name_ar' => 'طالب محدث', 'name_en' => 'Updated Student', 'guardian_name' => 'Guardian', 'guardian_phone' => '+201222222222', 'guardian_relationship' => 'mother'])->assertOk()->assertJsonPath('student.name_en', 'Updated Student');
        $this->actingAs($owner)->postJson('/api/students/'.$student->public_id.'/archive')->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'student.updated']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'student.archived']);
    }

    public function test_owner_can_create_a_branch_scoped_teaching_group_and_teacher_can_list_it(): void
    {
        $this->postJson('/api/register', ['name' => 'Owner', 'email' => 'group-owner@student.test', 'academy_name' => 'Academy', 'phone' => '+201000000002', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $owner = User::where('email', 'group-owner@student.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $year = $branch->academicYears()->firstOrFail();
        $level = $branch->educationalLevels()->firstOrFail();
        $subject = $branch->subjects()->firstOrFail();

        $this->actingAs($owner)->postJson('/api/teaching-groups', ['name_ar' => 'مجموعة الفيزياء', 'name_en' => 'Physics A', 'code' => 'PHY-A', 'academic_year_id' => $year->id, 'educational_level_id' => $level->id, 'subject_id' => $subject->id, 'max_capacity' => 30])->assertCreated()->assertJsonPath('group.code', 'PHY-A');
        $this->actingAs($owner)->getJson('/api/teaching-groups')->assertOk()->assertJsonPath('groups.0.active_students', 0)->assertJsonPath('groups.0.max_capacity', 30);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'teaching_group.created']);
    }

    public function test_attendance_session_accepts_group_scan_without_duplicate_rows(): void
    {
        $this->postJson('/api/register', ['name' => 'Owner', 'email' => 'attendance-owner@student.test', 'academy_name' => 'Academy', 'phone' => '+201000000003', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $owner = User::where('email', 'attendance-owner@student.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $group = $branch->teachingGroups()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name_ar' => 'مجموعة', 'name_en' => 'Group', 'code' => 'G-1', 'status' => 'active']);
        $student = $branch->students()->create(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'student_code' => 'STU-ATT-1', 'name_ar' => 'طالب', 'status' => 'active']);
        $absent = $branch->students()->create(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'student_code' => 'STU-ATT-2', 'name_ar' => 'Other', 'status' => 'active']);
        $group->students()->attach($student->id, ['status' => 'active', 'organization_id' => $organization->id, 'branch_id' => $branch->id, 'enrolled_on' => today()]);
        $group->students()->attach($absent->id, ['status' => 'active', 'organization_id' => $organization->id, 'branch_id' => $branch->id, 'enrolled_on' => today()]);

        $session = $this->actingAs($owner)->postJson('/api/attendance/sessions', ['teaching_group_id' => $group->id, 'session_date' => today()->toDateString()])->assertCreated()->json('session');
        $this->actingAs($owner)->postJson('/api/attendance/sessions/'.$session['id'].'/scan', ['student_code' => 'STU-ATT-1'])->assertOk()->assertJsonPath('duplicate', false);
        $this->actingAs($owner)->postJson('/api/attendance/sessions/'.$session['id'].'/scan', ['student_code' => 'STU-ATT-1'])->assertOk()->assertJsonPath('duplicate', true);
        $this->actingAs($owner)->postJson('/api/attendance/sessions/'.$session['id'].'/scan', ['student_code' => 'STU-ATT-1', 'status' => 'late'])->assertOk()->assertJsonPath('duplicate', true);
        $this->actingAs($owner)->getJson('/api/attendance/sessions/'.$session['id'].'/corrections')->assertOk()->assertJsonCount(1, 'corrections')->assertJsonPath('corrections.0.from_status', 'present')->assertJsonPath('corrections.0.to_status', 'late');
        $this->actingAs($owner)->putJson('/api/attendance/sessions/'.$session['id'].'/records', ['records' => [['student_id' => $absent->id, 'status' => 'late']]])->assertOk()->assertJsonPath('updated', 1);
        $this->actingAs($owner)->postJson('/api/attendance/sessions/'.$session['id'].'/close')->assertOk();
        $this->actingAs($owner)->postJson('/api/attendance/sessions/'.$session['id'].'/close')->assertOk()->assertJsonPath('already_closed', true);
        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $absent->id, 'status' => 'late', 'method' => 'manual']);
    }

    public function test_group_schedule_is_branch_scoped_and_rejects_time_conflicts(): void
    {
        $this->postJson('/api/register', ['name' => 'Owner', 'email' => 'schedule-owner@student.test', 'academy_name' => 'Academy', 'phone' => '+201000000004', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $owner = User::where('email', 'schedule-owner@student.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $group = $branch->teachingGroups()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name_ar' => 'مجموعة', 'name_en' => 'Schedule group', 'code' => 'SCH-1', 'status' => 'active']);
        $payload = ['teaching_group_id' => $group->id, 'day_of_week' => 2, 'starts_at' => '16:00', 'ends_at' => '18:00', 'mode' => 'onsite'];
        $this->actingAs($owner)->postJson('/api/teaching-groups/schedules', $payload)->assertCreated();
        $this->actingAs($owner)->postJson('/api/teaching-groups/schedules', [...$payload, 'starts_at' => '17:00', 'ends_at' => '19:00'])->assertUnprocessable();
        $this->actingAs($owner)->getJson('/api/teaching-groups/schedules')->assertOk()->assertJsonCount(1, 'schedules');
    }
}
