<?php

namespace Statamic\StaticSite;

use Spatie\Fork\Fork;

class ConcurrentTasks implements Tasks
{
    protected $fork;

    public function __construct(Fork $fork)
    {
        $this->fork = $fork;
    }

    public function run(...$closures)
    {
        return $this->fork->run(...$closures);
    }
}
