<?php

namespace Statamic\StaticSite;

class ConsecutiveTasks implements Tasks
{
    public function run(...$closures)
    {
        $results = [];

        foreach ($closures as $closure) {
            $results[] = $closure();
        }

        return $results;
    }
}
