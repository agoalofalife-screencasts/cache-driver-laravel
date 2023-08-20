<?php

namespace Tests\Feature;

use Tests\TestCase;

class BigCacheTest extends TestCase
{
    public function tearDown(): void
    {
        cache()->flush(); //.env.testing указан драйвер
        parent::tearDown();
    }
    // psr-12 не ругается на underscore потому-что папка с тестами не сканируется на предмет PSR-12
    public function test_expect_the_same_value_before_put_it(): void
    {
        cache(['youtube' => 'agoalofalife'], 10);

        $this->assertEquals(cache('youtube'), 'agoalofalife');
    }


    public function test_expect_get_null_because_value_was_expired(): void
    {
        cache(['youtube' => 'agoalofalife'], 0);

        $this->assertNull(cache('youtube'));
    }


    public function test_expect_exception_because_array_has_not_keys_string(): void
    {
        $this->expectException(\Exception::class);
        cache()->putMany(['word' => 'subscribe', 'my', 'channel'], 10);
    }

    public function test_expect_the_same_many_values_before_put_it(): void
    {
        $this->assertTrue(cache()->putMany(['channel' => 'agoalofalife', 'action' => 'subscribe'], 10));

        $this->assertContains('agoalofalife', cache()->many(['channel']));
        $this->assertContains('subscribe', cache()->many(['action']));
    }


    public function test_expect_increment_value_first_time_in_cache(): void
    {
        $this->assertEquals(cache()->increment('index', 2), 2);
    }


    public function test_expect_increment_value_second_time_in_cache(): void
    {
        cache()->increment('index', 3);
        $this->assertEquals(cache()->increment('index'), 4);
    }

    public function test_expect_decrement_value_first_time_in_cache(): void
    {
        $this->assertEquals(cache()->decrement('index'), -1);
    }

    public function test_expect_decrement_value_second_time_in_cache(): void
    {
        cache()->increment('index', 2);
        $this->assertEquals(cache()->decrement('index', 2), 0);
    }


    public function test_expect_clear_value(): void
    {
        cache()->put('name', 'Ilya', 10);

        $this->assertEquals(cache()->forget('name'), 200);
        $this->assertNull(cache()->get('name'));
    }

    public function test_expect_flush_all_storage(): void
    {
        cache('name', 'Ilya', 10);
        cache()->flush();

        $this->assertNull(cache('name'));
    }
}
