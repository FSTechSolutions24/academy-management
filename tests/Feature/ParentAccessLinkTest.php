<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentAccessLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_and_revokes_an_expiring_parent_link(): void
    {
        $this->postJson('/api/register', ['name' => 'Owner', 'email' => 'parent-links@test.test', 'academy_name' => 'Academy', 'phone' => '+201000000005', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $owner = User::where('email', 'parent-links@test.test')->firstOrFail();
        $owner->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $student = $branch->students()->create(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'student_code' => 'STU-PARENT-1', 'name_ar' => 'طالب', 'name_en' => 'Parent Student', 'status' => 'active']);

        $link = $this->actingAs($owner)->postJson('/api/parent-links', ['student_id' => $student->public_id, 'expires_in_days' => 7])->assertCreated()->json('link');
        $token = basename(parse_url($link['url'], PHP_URL_PATH));
        $this->getJson('/api/parent/'.$token)->assertOk()->assertJsonPath('student.student_code', 'STU-PARENT-1')->assertJsonStructure(['attendance' => ['total', 'present', 'late', 'excused', 'absent'], 'attendance_recent', 'results']);
        $this->actingAs($owner)->deleteJson('/api/parent-links/'.$link['id'])->assertOk();
        $this->getJson('/api/parent/'.$token)->assertNotFound();
    }
}
