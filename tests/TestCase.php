<?php

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            StatamicServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Statamic' => Statamic::class,
        ];
    }
}
