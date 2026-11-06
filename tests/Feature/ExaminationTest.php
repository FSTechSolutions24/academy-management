<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($teacher)->postJson('/api/examinations', ['title' => 'Midterm Physics', 'subject_id' => $subject->id, 'exam_date' => today()->addWeek()->toDateString(), 'total_marks' => 50])->assertCreated()->assertJsonPath('exam.status', 'draft')->assertJsonPath('exam.title', 'Midterm Physics');
        $this->actingAs($teacher)->getJson('/api/examinations')->assertOk()->assertJsonCount(1, 'exams')->assertJsonPath('exams.0.subject.id', $subject->id);
        $this->assertDatabaseHas('examinations', ['branch_id' => $branch->id, 'title' => 'Midterm Physics', 'status' => 'draft']);
    }
}
