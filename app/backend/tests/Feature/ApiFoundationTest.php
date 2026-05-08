<?php

namespace Tests\Feature;

use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_api_response_helper_returns_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Created', null, 201);
        $data = $response->getData(true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertNull($data['errors']);
        $this->assertNull($data['meta']);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_force_json_response_middleware_sets_accept_header(): void
    {
        Route::middleware('api')->get('/api/v1/test-json-header', function (\Illuminate\Http\Request $request) {
            return response()->json(['accept' => $request->header('Accept')]);
        });

        $response = $this->get('/api/v1/test-json-header');
        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->json('accept'));
    }

    public function test_unhandled_exception_returns_sanitized_500_envelope(): void
    {
        Route::middleware('api')->get('/api/v1/test-exception', function () {
            throw new \RuntimeException('Sensitive internal error details');
        });

        $response = $this->getJson('/api/v1/test-exception');
        $response->assertStatus(500)
            ->assertExactJson([
                'data'    => null,
                'message' => 'An unexpected error occurred.',
                'errors'  => null,
                'meta'    => null,
            ]);

        $this->assertStringNotContainsString('Sensitive internal error details', $response->content());
    }

    public function test_validation_exception_returns_422_with_errors(): void
    {
        Route::middleware('api')->post('/api/v1/test-validation', function (\Illuminate\Http\Request $request) {
            $request->validate(['name' => 'required']);
        });

        $response = $this->postJson('/api/v1/test-validation', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'data',
                'message',
                'errors' => ['name'],
                'meta',
            ]);
    }

    public function test_user_repository_resolves_from_container(): void
    {
        $repo = app(UserRepositoryInterface::class);
        $this->assertInstanceOf(UserRepositoryInterface::class, $repo);
    }

    public function test_item_repository_resolves_from_container(): void
    {
        $repo = app(ItemRepositoryInterface::class);
        $this->assertInstanceOf(ItemRepositoryInterface::class, $repo);
    }
}
