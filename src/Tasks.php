<?php

namespace Statamic\StaticSite;

interface Tasks
{
    public function run(...$closures);
}
