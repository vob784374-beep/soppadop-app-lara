<?php

namespace Tests\Feature\Lms;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private Course $course;
    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->instructor = User::factory()->create();
        $this->student    = User::factory()->create();
        $this->instructor->assignRole('instructor');
        $this->student->assignRole('student');

        $this->course     = Course::factory()->create(['instructor_id' => $this->instructor->id]);
        $this->assignment = Assignment::factory()->create(['course_id' => $this->course->id]);
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }

    private function enroll(User $student, Course $course): void
    {
        Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
    }

    // ── POST /assignments/{id}/submissions ────────────────────────────────────

    public function test_enrolled_student_can_submit_before_deadline(): void
    {
        $this->enroll($this->student, $this->course);

        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/assignments/{$this->assignment->id}/submissions", [
                'content' => 'My answer here.',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('submissions', [
            'assignment_id' => $this->assignment->id,
            'student_id'    => $this->student->id,
        ]);
    }

    public function test_student_cannot_submit_after_deadline(): void
    {
        $this->enroll($this->student, $this->course);
        $this->assignment->update(['deadline' => now()->subDay()]);

        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/assignments/{$this->assignment->id}/submissions", [
                'content' => 'Late answer.',
            ])
            ->assertStatus(403);
    }

    public function test_non_enrolled_student_cannot_submit(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/assignments/{$this->assignment->id}/submissions", [
                'content' => 'Not enrolled.',
            ])
            ->assertStatus(403);
    }

    public function test_instructor_cannot_submit(): void
    {
        $this->withToken($this->tokenFor($this->instructor))
            ->postJson("/api/v1/assignments/{$this->assignment->id}/submissions", [
                'content' => 'Instructor trying to submit.',
            ])
            ->assertStatus(403);
    }

    // ── GET /assignments/{id}/submissions ─────────────────────────────────────

    public function test_instructor_can_list_submissions_for_own_assignment(): void
    {
        $this->enroll($this->student, $this->course);
        Submission::create([
            'assignment_id' => $this->assignment->id,
            'student_id'    => $this->student->id,
            'content'       => 'Answer',
            'submitted_at'  => now(),
        ]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/assignments/{$this->assignment->id}/submissions")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_student_cannot_list_all_submissions(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/assignments/{$this->assignment->id}/submissions")
            ->assertStatus(403);
    }

    // ── GET /submissions/{id} ─────────────────────────────────────────────────

    public function test_student_can_view_own_submission(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id'    => $this->student->id,
        ]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/submissions/{$submission->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $submission->id);
    }

    public function test_student_cannot_view_another_students_submission(): void
    {
        $other = User::factory()->create();
        $other->assignRole('student');

        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id'    => $other->id,
        ]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/submissions/{$submission->id}")
            ->assertStatus(403);
    }

    public function test_instructor_can_view_submission_in_own_course(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id'    => $this->student->id,
        ]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/submissions/{$submission->id}")
            ->assertStatus(200);
    }

    // ── PATCH /submissions/{id}/grade ─────────────────────────────────────────

    public function test_instructor_can_grade_submission(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id'    => $this->student->id,
        ]);

        $this->withToken($this->tokenFor($this->instructor))
            ->patchJson("/api/v1/submissions/{$submission->id}/grade", ['grade' => 88])
            ->assertStatus(200)
            ->assertJsonPath('data.grade', 88);

        $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'grade' => 88]);
    }

    public function test_student_cannot_grade_submission(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id'    => $this->student->id,
        ]);

        $this->withToken($this->tokenFor($this->student))
            ->patchJson("/api/v1/submissions/{$submission->id}/grade", ['grade' => 100])
            ->assertStatus(403);
    }
}
