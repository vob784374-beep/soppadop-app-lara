<?php

namespace Tests\Feature\Lms;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->instructor = User::factory()->create();
        $this->student    = User::factory()->create();
        $this->instructor->assignRole('instructor');
        $this->student->assignRole('student');

        $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }

    private function enroll(User $student, Course $course): void
    {
        Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
    }

    // ── GET /courses/{courseId}/assignments ───────────────────────────────────

    public function test_instructor_can_list_assignments_for_own_course(): void
    {
        Assignment::factory()->count(2)->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/courses/{$this->course->id}/assignments")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_enrolled_student_can_list_assignments(): void
    {
        $this->enroll($this->student, $this->course);
        Assignment::factory()->count(2)->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$this->course->id}/assignments")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_non_enrolled_student_cannot_list_assignments(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$this->course->id}/assignments")
            ->assertStatus(403);
    }

    public function test_list_assignments_requires_auth(): void
    {
        $this->getJson("/api/v1/courses/{$this->course->id}/assignments")->assertStatus(401);
    }

    // ── POST /courses/{courseId}/assignments ──────────────────────────────────

    public function test_instructor_can_create_assignment(): void
    {
        $deadline = now()->addDays(14)->toDateTimeString();

        $this->withToken($this->tokenFor($this->instructor))
            ->postJson("/api/v1/courses/{$this->course->id}/assignments", [
                'title'       => 'Writing Task 1',
                'description' => 'Describe the chart',
                'deadline'    => $deadline,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Writing Task 1');

        $this->assertDatabaseHas('assignments', [
            'title'     => 'Writing Task 1',
            'course_id' => $this->course->id,
        ]);
    }

    public function test_student_cannot_create_assignment(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/courses/{$this->course->id}/assignments", [
                'title'    => 'Nope',
                'deadline' => now()->addDays(7)->toDateTimeString(),
            ])
            ->assertStatus(403);
    }

    // ── GET /assignments/{id} ─────────────────────────────────────────────────

    public function test_instructor_can_view_own_assignment(): void
    {
        $assignment = Assignment::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $assignment->id);
    }

    public function test_enrolled_student_can_view_assignment(): void
    {
        $this->enroll($this->student, $this->course);
        $assignment = Assignment::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertStatus(200);
    }

    public function test_non_enrolled_student_cannot_view_assignment(): void
    {
        $assignment = Assignment::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertStatus(403);
    }

    public function test_show_assignment_requires_auth(): void
    {
        $assignment = Assignment::factory()->create(['course_id' => $this->course->id]);
        $this->getJson("/api/v1/assignments/{$assignment->id}")->assertStatus(401);
    }
}
