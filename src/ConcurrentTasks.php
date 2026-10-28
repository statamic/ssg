<?php

namespace Statamic\StaticSite;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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
            DB::reconnect($connection);
        }
    }
}
