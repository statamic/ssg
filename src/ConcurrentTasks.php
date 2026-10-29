<?php

namespace Statamic\StaticSite;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
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
        return $this->fork
            ->before(child: fn () => $this->reconnectInChild())
            ->run(...$closures);
    }

    private function reconnectInChild(): void
    {
        foreach (array_keys(Redis::connections() ?? []) as $connection) {
            Redis::purge($connection);
        }

        foreach (array_keys(DB::getConnections()) as $connection) {
            // Connections made at runtime (eg. Statamic's fake SQL query logging) can't be reconnected.
            if (! Arr::get(config('database.connections'), Str::before($connection, '::'))) {
                continue;
            }

            DB::reconnect($connection);
        }
    }
}
