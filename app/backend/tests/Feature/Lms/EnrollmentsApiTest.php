<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentsApiTest extends TestCase
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

    // ── POST /courses/{courseId}/enroll ───────────────────────────────────────

    public function test_student_can_enroll_in_course(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/courses/{$this->course->id}/enroll")
            ->assertStatus(201);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $this->student->id,
            'course_id'  => $this->course->id,
        ]);
    }

    public function test_re_enrollment_is_idempotent(): void
    {
        Enrollment::create(['student_id' => $this->student->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);

        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/courses/{$this->course->id}/enroll")
            ->assertStatus(201);

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_enroll_in_nonexistent_course_returns_404(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson('/api/v1/courses/99999/enroll')
            ->assertStatus(404);
    }

    public function test_instructor_cannot_enroll(): void
    {
        $this->withToken($this->tokenFor($this->instructor))
            ->postJson("/api/v1/courses/{$this->course->id}/enroll")
            ->assertStatus(403);
    }

    public function test_enroll_requires_auth(): void
    {
        $this->postJson("/api/v1/courses/{$this->course->id}/enroll")->assertStatus(401);
    }

    public function test_after_enrollment_student_can_access_lessons(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/courses/{$this->course->id}/enroll")
            ->assertStatus(201);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$this->course->id}/lessons")
            ->assertStatus(200);
    }
}
