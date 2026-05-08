<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin      = User::factory()->create();
        $this->instructor = User::factory()->create();
        $this->student    = User::factory()->create();

        $this->admin->assignRole('admin');
        $this->instructor->assignRole('instructor');
        $this->student->assignRole('student');
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }

    // ── GET /courses ──────────────────────────────────────────────────────────

    public function test_admin_sees_all_courses(): void
    {
        Course::factory()->count(3)->create();

        $this->withToken($this->tokenFor($this->admin))
            ->getJson('/api/v1/courses')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_instructor_sees_only_own_courses(): void
    {
        Course::factory()->create(['instructor_id' => $this->instructor->id]);
        Course::factory()->create(); // another instructor's course

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson('/api/v1/courses')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_student_sees_only_enrolled_courses(): void
    {
        $enrolled   = Course::factory()->create(['instructor_id' => $this->instructor->id]);
        Course::factory()->create(['instructor_id' => $this->instructor->id]);

        Enrollment::create([
            'student_id'  => $this->student->id,
            'course_id'   => $enrolled->id,
            'enrolled_at' => now(),
        ]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson('/api/v1/courses')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_courses_list_requires_auth(): void
    {
        $this->getJson('/api/v1/courses')->assertStatus(401);
    }

    // ── POST /courses ─────────────────────────────────────────────────────────

    public function test_instructor_can_create_course(): void
    {
        $this->withToken($this->tokenFor($this->instructor))
            ->postJson('/api/v1/courses', ['title' => 'IELTS Writing', 'description' => 'Band 7+'])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'IELTS Writing');

        $this->assertDatabaseHas('courses', [
            'title'         => 'IELTS Writing',
            'instructor_id' => $this->instructor->id,
        ]);
    }

    public function test_student_cannot_create_course(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson('/api/v1/courses', ['title' => 'Nope'])
            ->assertStatus(403);
    }

    // ── GET /courses/{id} ─────────────────────────────────────────────────────

    public function test_admin_can_view_any_course(): void
    {
        $course = Course::factory()->create();

        $this->withToken($this->tokenFor($this->admin))
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $course->id);
    }

    public function test_instructor_can_view_own_course(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertStatus(200);
    }

    public function test_student_can_view_enrolled_course(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
        Enrollment::create(['student_id' => $this->student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertStatus(200);
    }

    public function test_student_cannot_view_non_enrolled_course(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertStatus(403);
    }

    // ── PATCH /courses/{id} ───────────────────────────────────────────────────

    public function test_instructor_can_update_own_course(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->patchJson("/api/v1/courses/{$course->id}", ['title' => 'Updated Title'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_instructor_cannot_update_another_instructors_course(): void
    {
        $other   = User::factory()->create();
        $other->assignRole('instructor');
        $course  = Course::factory()->create(['instructor_id' => $other->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->patchJson("/api/v1/courses/{$course->id}", ['title' => 'Stolen'])
            ->assertStatus(403);
    }

    // ── DELETE /courses/{id} ──────────────────────────────────────────────────

    public function test_admin_can_delete_course(): void
    {
        $course = Course::factory()->create();

        $this->withToken($this->tokenFor($this->admin))
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_instructor_cannot_delete_course(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertStatus(403);
    }
}
