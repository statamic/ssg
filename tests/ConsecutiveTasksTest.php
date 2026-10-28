<?php

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\StaticSite\ConsecutiveTasks;

class ConsecutiveTasksTest extends TestCase
{
    #[Test]
    public function it_runs_callbacks()
    {
        $callbacksRan = 0;

        $one = function () use (&$callbacksRan) {
            $callbacksRan++;

            return 'one';
        };

        $two = function () use (&$callbacksRan) {
            $callbacksRan++;

            return 'two';
        };

        $results = (new ConsecutiveTasks)->run($one, $two);

        $this->assertEquals(['one', 'two'], $results);
        $this->assertEquals(2, $callbacksRan);
    }
}
