<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExaminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_a_branch_scoped_draft_exam(): void
    {
        $this->postJson('/api/register', ['name' => 'Exam Teacher', 'email' => 'exam-teacher@test.local', 'academy_name' => 'Exam Academy', 'phone' => '+201000000009', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026'])->assertCreated();
        $teacher = User::where('email', 'exam-teacher@test.local')->firstOrFail();
        $teacher->markEmailAsVerified();
        $organization = Organization::firstOrFail();
        $branch = $organization->branches()->firstOrFail();
        $subject = $branch->subjects()->firstOrFail();
        $group = $branch->teachingGroups()->create(['organization_id' => $organization->id, 'public_id' => (string) \Illuminate\Support\Str::ulid(), 'name_ar' => 'مجموعة', 'name_en' => 'Physics A', 'code' => 'PHY-A', 'subject_id' => $subject->id, 'status' => 'active']);

        $exam = $this->actingAs($teacher)->postJson('/api/examinations', ['title' => 'Midterm Physics', 'subject_id' => $subject->id, 'teaching_group_ids' => [$group->id], 'exam_date' => today()->addWeek()->toDateString(), 'total_marks' => 50])->assertCreated()->assertJsonPath('exam.status', 'draft')->assertJsonPath('exam.title', 'Midterm Physics')->json('exam');
        $this->actingAs($teacher)->getJson('/api/examinations')->assertOk()->assertJsonCount(1, 'exams')->assertJsonPath('exams.0.subject.id', $subject->id);
        $this->actingAs($teacher)->getJson('/api/examinations/'.$exam['id'].'/marks')->assertOk()->assertJsonPath('students', []);
        $this->actingAs($teacher)->getJson('/api/examinations/performance')->assertOk()->assertJsonStructure(['summary' => ['class_average', 'published_exams', 'students_at_risk'], 'exam_stats', 'subject_stats', 'at_risk']);
        $student = $branch->students()->create(['public_id' => (string) \Illuminate\Support\Str::ulid(), 'organization_id' => $organization->id, 'student_code' => 'STU-EXAM-1', 'name_ar' => 'طالب', 'name_en' => 'Exam Student', 'status' => 'active']);
        $group->students()->attach($student->id, ['organization_id' => $organization->id, 'branch_id' => $branch->id, 'status' => 'active', 'enrolled_on' => today()]);
        $this->actingAs($teacher)->getJson('/api/examinations/'.$exam['id'].'/attendance')->assertOk()->assertJsonCount(1, 'students');
        $this->actingAs($teacher)->putJson('/api/examinations/'.$exam['id'].'/attendance', ['attendance' => [['student_id' => $student->id, 'status' => 'present']]])->assertOk()->assertJsonPath('updated', 1);
        $this->actingAs($teacher)->post('/api/examinations/'.$exam['id'].'/marks/import', ['file' => UploadedFile::fake()->createWithContent('marks.csv', "student_code,mark,comment\nSTU-EXAM-1,42,Good work\nUNKNOWN,10,Invalid\n")])->assertOk()->assertJsonPath('imported', 1)->assertJsonPath('errors.0.row', 3);
        $this->actingAs($teacher)->postJson('/api/examinations/'.$exam['id'].'/status', ['status' => 'published'])->assertOk()->assertJsonPath('exam.status', 'published');
        $this->actingAs($teacher)->postJson('/api/examinations/'.$exam['id'].'/status', ['status' => 'locked'])->assertOk()->assertJsonPath('exam.status', 'locked');
        $this->actingAs($teacher)->putJson('/api/examinations/'.$exam['id'].'/marks', ['marks' => []])->assertStatus(422);
        $this->assertDatabaseHas('examinations', ['branch_id' => $branch->id, 'title' => 'Midterm Physics', 'status' => 'locked']);
    }
}
