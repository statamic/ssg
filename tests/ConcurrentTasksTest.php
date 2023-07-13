<?php

namespace Tests;

use Spatie\Fork\Fork;
use Statamic\StaticSite\ConcurrentTasks;

class ConcurrentTasksTest extends TestCase
{
    /** @test */
    public function it_runs_callbacks()
    {
        $one = function () {
            return 'one';
        };

        $two = function () {
            return 'two';
        };

        $fork = $this->mock(Fork::class);
        $fork->shouldReceive('run')->once()->with($one, $two)->andReturn([$one(), $two()]);

        $results = (new ConcurrentTasks($fork))->run($one, $two);

        $this->assertEquals(['one', 'two'], $results);
    }
}
