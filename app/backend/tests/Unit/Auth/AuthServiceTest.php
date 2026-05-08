<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\Contracts\JwtServiceInterface;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private JwtServiceInterface $jwt;
    private UserRepositoryInterface $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->jwt   = Mockery::mock(JwtServiceInterface::class);
        $this->users = Mockery::mock(UserRepositoryInterface::class);

        $this->authService = new AuthService($this->users, $this->jwt);
    }

    public function test_register_creates_user_and_returns_token_payload(): void
    {
        $user = User::factory()->create(['name' => 'Bang', 'email' => 'bang@test.com']);

        $this->users->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($d) => $d['email'] === 'bang@test.com'))
            ->andReturn($user);

        $this->jwt->shouldReceive('fromUser')->once()->with($user)->andReturn('tok');
        $this->jwt->shouldReceive('tokenResponse')->once()->with('tok')->andReturn([
            'token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 3600,
        ]);

        $result = $this->authService->register([
            'name' => 'Bang', 'email' => 'bang@test.com', 'password' => 'password123',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('bearer', $result['token_type']);
        $this->assertEquals(3600, $result['expires_in']);
    }

    public function test_login_returns_user_and_token_payload(): void
    {
        $user = new User(['email' => 'bang@test.com']);

        $this->jwt->shouldReceive('attempt')->once()->with('bang@test.com', 'secret')->andReturn('tok');
        $this->jwt->shouldReceive('user')->once()->andReturn($user);
        $this->jwt->shouldReceive('tokenResponse')->once()->andReturn([
            'token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 3600,
        ]);

        $result = $this->authService->login('bang@test.com', 'secret');

        $this->assertSame($user, $result['user']);
        $this->assertEquals('tok', $result['token']);
    }

    public function test_login_propagates_authentication_exception(): void
    {
        $this->jwt->shouldReceive('attempt')
            ->once()
            ->andThrow(new AuthenticationException('Invalid credentials.'));

        $this->expectException(AuthenticationException::class);

        $this->authService->login('x@x.com', 'wrong');
    }

    public function test_refresh_delegates_to_jwt_service(): void
    {
        $this->jwt->shouldReceive('refresh')->once()->andReturn('new.tok');
        $this->jwt->shouldReceive('tokenResponse')->once()->with('new.tok')->andReturn([
            'token' => 'new.tok', 'token_type' => 'bearer', 'expires_in' => 3600,
        ]);

        $result = $this->authService->refresh();

        $this->assertEquals('new.tok', $result['token']);
    }

    public function test_logout_delegates_to_jwt_invalidate(): void
    {
        $this->jwt->shouldReceive('invalidate')->once();

        $this->authService->logout();
    }
}
