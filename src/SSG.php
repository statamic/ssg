<?php

namespace Statamic\StaticSite;

use Illuminate\Support\Facades\Facade;

class SSG extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Generator::class;
    }
}
