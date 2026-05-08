<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JwtServiceTest extends TestCase
{
    use RefreshDatabase;
    private JwtService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = app(JwtService::class);
    }

    public function test_from_user_returns_non_empty_token(): void
    {
        $user = User::factory()->create();

        $token = $this->jwt->fromUser($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function test_attempt_with_valid_credentials_returns_token(): void
    {
        User::factory()->create(['email' => 'test@jwt.com', 'password' => 'secret123']);

        $token = $this->jwt->attempt('test@jwt.com', 'secret123');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function test_attempt_with_invalid_password_throws(): void
    {
        User::factory()->create(['email' => 'test@jwt.com', 'password' => 'secret123']);

        $this->expectException(AuthenticationException::class);

        $this->jwt->attempt('test@jwt.com', 'wrongpassword');
    }

    public function test_attempt_with_unknown_email_throws(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->jwt->attempt('nobody@example.com', 'secret123');
    }

    public function test_invalidate_does_not_throw(): void
    {
        $user = User::factory()->create();
        $this->jwt->fromUser($user);

        $this->expectNotToPerformAssertions();
        $this->jwt->invalidate();
    }

    public function test_token_response_has_required_keys(): void
    {
        $response = $this->jwt->tokenResponse('some.jwt.token');

        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('token_type', $response);
        $this->assertArrayHasKey('expires_in', $response);
        $this->assertEquals('bearer', $response['token_type']);
        $this->assertEquals('some.jwt.token', $response['token']);
    }

    public function test_ttl_returns_positive_integer(): void
    {
        $ttl = $this->jwt->ttl();

        $this->assertIsInt($ttl);
        $this->assertGreaterThan(0, $ttl);
    }
}
