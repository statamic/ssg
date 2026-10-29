<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Fork\Fork;
use Statamic\StaticSite\ConcurrentTasks;

class ConcurrentTasksTest extends TestCase
{
    #[Test]
    public function it_runs_callbacks()
    {
        $one = function () {
            return 'one';
        };

        $two = function () {
            return 'two';
        };

        $fork = $this->mock(Fork::class);
        $fork->shouldReceive('before')->once()->andReturnSelf();
        $fork->shouldReceive('run')->once()->with($one, $two)->andReturn([$one(), $two()]);

        $results = (new ConcurrentTasks($fork))->run($one, $two);

        $this->assertEquals(['one', 'two'], $results);
    }

    /**
     * @see https://github.com/statamic/ssg/issues/61
     */
    #[Test]
    public function it_gives_each_child_its_own_connections()
    {
        $beforeChild = null;

        $fork = $this->mock(Fork::class);
        $fork->shouldReceive('before')->once()->andReturnUsing(function ($child) use (&$beforeChild, $fork) {
            $beforeChild = $child;

            return $fork;
        });
        $fork->shouldReceive('run')->once()->andReturn([]);

        (new ConcurrentTasks($fork))->run(fn () => null);

        $parentPdo = DB::connection()->getPdo();

        Redis::shouldReceive('connections')->once()->andReturn(['default' => null, 'cache' => null]);
        Redis::shouldReceive('purge')->once()->with('default');
        Redis::shouldReceive('purge')->once()->with('cache');

        $beforeChild();

        $this->assertNotSame($parentPdo, DB::connection()->getPdo());
    }

    #[Test]
    public function it_skips_connections_that_are_not_configured()
    {
        $beforeChild = null;

        $fork = $this->mock(Fork::class);
        $fork->shouldReceive('before')->once()->andReturnUsing(function ($child) use (&$beforeChild, $fork) {
            $beforeChild = $child;

            return $fork;
        });
        $fork->shouldReceive('run')->once()->andReturn([]);

        (new ConcurrentTasks($fork))->run(fn () => null);

        // Statamic's fake SQL query logging registers a connection like this when app.debug is on.
        $fake = DB::connectUsing('fake', ['driver' => 'sqlite', 'database' => ':memory:']);

        $beforeChild();

        $this->assertSame($fake, DB::connection('fake'));
    }
}
