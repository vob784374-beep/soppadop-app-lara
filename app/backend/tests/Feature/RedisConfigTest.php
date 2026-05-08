<?php

namespace Tests\Feature;

use Tests\Support\TestJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'redis']);
        config(['queue.default' => 'redis']);
    }

    protected function tearDown(): void
    {
        Cache::forget('redis_test_key');
        Redis::connection('default')->del('queues:default');
        parent::tearDown();
    }

    public function test_cache_stores_and_retrieves_via_redis(): void
    {
        Cache::put('redis_test_key', 'hello_redis', 60);
        $this->assertEquals('hello_redis', Cache::get('redis_test_key'));
    }

    public function test_queue_dispatches_job_to_redis(): void
    {
        Redis::connection('default')->del('queues:default');

        TestJob::dispatch();

        $this->assertEquals(1, Redis::connection('default')->llen('queues:default'));
    }
}
