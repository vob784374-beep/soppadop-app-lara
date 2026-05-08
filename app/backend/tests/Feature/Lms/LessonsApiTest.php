<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonsApiTest extends TestCase
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

    // ── GET /courses/{courseId}/lessons ───────────────────────────────────────

    public function test_instructor_can_list_lessons_for_own_course(): void
    {
        Lesson::factory()->count(3)->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/courses/{$this->course->id}/lessons")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_enrolled_student_can_list_lessons(): void
    {
        $this->enroll($this->student, $this->course);
        Lesson::factory()->count(2)->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$this->course->id}/lessons")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_non_enrolled_student_cannot_list_lessons(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/courses/{$this->course->id}/lessons")
            ->assertStatus(403);
    }

    public function test_list_lessons_requires_auth(): void
    {
        $this->getJson("/api/v1/courses/{$this->course->id}/lessons")->assertStatus(401);
    }

    // ── POST /courses/{courseId}/lessons ──────────────────────────────────────

    public function test_instructor_can_create_lesson(): void
    {
        $this->withToken($this->tokenFor($this->instructor))
            ->postJson("/api/v1/courses/{$this->course->id}/lessons", [
                'title'   => 'Lesson 1',
                'content' => 'Content here',
                'order'   => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Lesson 1');

        $this->assertDatabaseHas('lessons', ['title' => 'Lesson 1', 'course_id' => $this->course->id]);
    }

    public function test_student_cannot_create_lesson(): void
    {
        $this->withToken($this->tokenFor($this->student))
            ->postJson("/api/v1/courses/{$this->course->id}/lessons", ['title' => 'Nope', 'order' => 1])
            ->assertStatus(403);
    }

    public function test_instructor_cannot_create_lesson_in_another_instructors_course(): void
    {
        $other = User::factory()->create();
        $other->assignRole('instructor');

        $this->withToken($this->tokenFor($other))
            ->postJson("/api/v1/courses/{$this->course->id}/lessons", ['title' => 'Hijack', 'order' => 1])
            ->assertStatus(403);
    }

    // ── GET /lessons/{id} ─────────────────────────────────────────────────────

    public function test_instructor_can_view_lesson_in_own_course(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->getJson("/api/v1/lessons/{$lesson->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $lesson->id);
    }

    public function test_enrolled_student_can_view_lesson(): void
    {
        $this->enroll($this->student, $this->course);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/lessons/{$lesson->id}")
            ->assertStatus(200);
    }

    public function test_non_enrolled_student_cannot_view_lesson(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->student))
            ->getJson("/api/v1/lessons/{$lesson->id}")
            ->assertStatus(403);
    }

    // ── PATCH /lessons/{id} ───────────────────────────────────────────────────

    public function test_instructor_can_update_own_lesson(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->patchJson("/api/v1/lessons/{$lesson->id}", ['title' => 'Updated'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_instructor_cannot_update_lesson_in_another_course(): void
    {
        $other  = User::factory()->create();
        $other->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $other->id]);
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        $this->withToken($this->tokenFor($this->instructor))
            ->patchJson("/api/v1/lessons/{$lesson->id}", ['title' => 'Stolen'])
            ->assertStatus(403);
    }
}
