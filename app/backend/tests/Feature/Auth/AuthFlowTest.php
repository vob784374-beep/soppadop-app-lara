<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function test_register_returns_201_with_token_payload(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'Bang',
            'email'                 => 'bang@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type', 'expires_in'],
                'message',
                'errors',
                'meta',
            ])
            ->assertJson(['data' => ['token_type' => 'bearer']])
            ->assertJsonPath('errors', null);
    }

    public function test_register_422_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@test.com']);

        $this->postJson('/api/v1/register', [
            'name'                  => 'Bang',
            'email'                 => 'dup@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    public function test_register_422_on_password_mismatch(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Bang',
            'email'                 => 'bang@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'different',
        ])->assertStatus(422);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_returns_200_with_token_payload(): void
    {
        User::factory()->create(['email' => 'bang@test.com', 'password' => 'password123']);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'bang@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['user', 'token', 'token_type', 'expires_in'],
            ])
            ->assertJsonPath('data.token_type', 'bearer');
    }

    public function test_login_401_on_wrong_password(): void
    {
        User::factory()->create(['email' => 'bang@test.com', 'password' => 'password123']);

        $this->postJson('/api/v1/login', [
            'email'    => 'bang@test.com',
            'password' => 'wrongpass',
        ])->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_login_401_on_unknown_email(): void
    {
        $this->postJson('/api/v1/login', [
            'email'    => 'nobody@test.com',
            'password' => 'password123',
        ])->assertStatus(401);
    }

    // ── Token validation ──────────────────────────────────────────────────────

    public function test_protected_route_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/profile')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Token is invalid or missing.');
    }

    public function test_protected_route_returns_200_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    // ── Refresh ───────────────────────────────────────────────────────────────

    public function test_refresh_returns_new_token(): void
    {
        $user  = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withToken($token)->postJson('/api/v1/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_in']]);
    }

    public function test_old_token_rejected_after_refresh(): void
    {
        $user     = User::factory()->create();
        $oldToken = auth('api')->login($user);

        $this->withToken($oldToken)->postJson('/api/v1/refresh');

        $this->withToken($oldToken)->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_returns_200(): void
    {
        $user  = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/v1/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    public function test_token_rejected_after_logout(): void
    {
        $user  = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/v1/logout');

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertStatus(401);
    }
}
