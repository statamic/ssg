<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Statamic;

class TestCase extends OrchestraTestCase
{
    protected $fixturePath = __DIR__.'/Fixtures';
    protected $siteFixture = 'site';

    protected function getPackageProviders($app)
    {
        return [
            \Statamic\Providers\StatamicServiceProvider::class,
            \Wilderborn\Partyline\ServiceProvider::class,
            \Statamic\StaticSite\ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Statamic' => Statamic::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = app(Filesystem::class);

        $this->copyDirectoryFromFixture('resources');
        $this->copyDirectoryFromSiteFixture('content');

        $this->app->instance('fork-installed', false);
    }

    protected function copyDirectoryFromFixture($directory, $site = null)
    {
        if (base_path($directory)) {
            $this->files->deleteDirectory(base_path($directory));
        }

        $origin = vsprintf('%s/%s%s', [
            $this->fixturePath,
            $site ? "{$site}/" : '',
            $directory,
        ]);

        $this->files->copyDirectory($origin, base_path($directory));
    }

    protected function copyDirectoryFromSiteFixture($directory)
    {
        $this->copyDirectoryFromFixture($directory, $this->siteFixture);
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $configs = [
            'assets', 'cp', 'forms', 'routes', 'static_caching',
            'sites', 'stache', 'system', 'users',
        ];

        foreach ($configs as $config) {
            $app['config']->set("statamic.$config", require(__DIR__."/../vendor/statamic/cms/config/{$config}.php"));
        }
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.driver', 'statamic');
        $app['config']->set('statamic.users.repository', 'file');
    }
}
