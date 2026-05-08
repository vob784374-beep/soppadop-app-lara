<?php

namespace Tests\Feature;

use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    public function test_json_stderr_channel_produces_structured_output(): void
    {
        $stream = fopen('php://memory', 'r+');

        $logger = new \Monolog\Logger('test');
        $handler = new \Monolog\Handler\StreamHandler($stream);
        $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
        $logger->pushHandler($handler);

        $logger->info('observability test', ['story' => '1.8']);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('level_name', $decoded);
        $this->assertArrayHasKey('datetime', $decoded);
        $this->assertArrayHasKey('context', $decoded);
        $this->assertEquals('observability test', $decoded['message']);
        $this->assertEquals('INFO', $decoded['level_name']);
    }

    public function test_sentry_dsn_reads_from_environment(): void
    {
        $this->assertEquals(env('SENTRY_DSN', ''), config('sentry.dsn'));
    }
}
