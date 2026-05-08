<?php

namespace Tests\Feature\Lms;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacAbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── Seeder correctness ────────────────────────────────────────────────────

    public function test_seeder_creates_three_roles(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'admin',      'guard_name' => 'api']);
        $this->assertDatabaseHas('roles', ['name' => 'instructor', 'guard_name' => 'api']);
        $this->assertDatabaseHas('roles', ['name' => 'student',    'guard_name' => 'api']);
    }

    public function test_admin_has_all_permissions(): void
    {
        $admin = Role::findByName('admin', 'api');
        $this->assertGreaterThanOrEqual(16, $admin->permissions->count());
    }

    public function test_instructor_has_correct_permissions(): void
    {
        $instructor = Role::findByName('instructor', 'api');
        $names      = $instructor->permissions->pluck('name');

        $this->assertTrue($names->contains('create-courses'));
        $this->assertTrue($names->contains('edit-courses'));
        $this->assertTrue($names->contains('create-lessons'));
        $this->assertTrue($names->contains('grade-submissions'));
        $this->assertFalse($names->contains('enroll-courses'));
        $this->assertFalse($names->contains('submit-assignments'));
    }

    public function test_student_has_correct_permissions(): void
    {
        $student = Role::findByName('student', 'api');
        $names   = $student->permissions->pluck('name');

        $this->assertTrue($names->contains('view-courses'));
        $this->assertTrue($names->contains('enroll-courses'));
        $this->assertTrue($names->contains('submit-assignments'));
        $this->assertFalse($names->contains('create-courses'));
        $this->assertFalse($names->contains('grade-submissions'));
    }

    // ── Registration auto-assigns student role ─────────────────────────────────

    public function test_registered_user_is_assigned_student_role(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'test@test.com')->firstOrFail();
        $this->assertTrue($user->hasRole('student', 'api'));
    }

    // ── CheckPermission middleware ─────────────────────────────────────────────

    public function test_permission_middleware_blocks_student_from_creating_course(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $token = auth('api')->login($student);

        $this->withToken($token)
            ->postJson('/api/v1/courses', ['title' => 'Test', 'description' => 'X'])
            ->assertStatus(403);
    }

    public function test_permission_middleware_allows_instructor_to_create_course(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $token = auth('api')->login($instructor);

        $this->withToken($token)
            ->postJson('/api/v1/courses', ['title' => 'My Course', 'description' => 'Desc'])
            ->assertStatus(201);
    }

    public function test_admin_bypasses_all_permission_checks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = auth('api')->login($admin);

        $this->withToken($token)
            ->postJson('/api/v1/courses', ['title' => 'Admin Course', 'description' => 'Desc'])
            ->assertStatus(201);
    }
}
