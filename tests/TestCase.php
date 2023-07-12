<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Statamic;

class TestCase extends OrchestraTestCase
{
    protected $siteFixturePath = __DIR__.'/Fixtures/site';

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

        $this->copyDirectoryFromFixture('content');
        $this->copyDirectoryFromFixture('resources');
    }

    protected function copyDirectoryFromFixture($directory)
    {
        if (base_path($directory)) {
            $this->files->deleteDirectory(base_path($directory));
        }

        $this->files->copyDirectory("{$this->siteFixturePath}/{$directory}", base_path($directory));
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

        $files = new Filesystem;

        $files->copyDirectory(__DIR__.'/../vendor/statamic/cms/config', config_path('statamic'));

        $configs = [
            'filesystems',
            'statamic/users',
            'statamic/stache',
            'statamic/sites',
        ];

        foreach ($configs as $config) {
            $files->delete(config_path("{$config}.php"));
            $files->copy("{$this->siteFixturePath}/config/{$config}.php", config_path("{$config}.php"));
            $app['config']->set(str_replace('/', '.', $config), require("{$this->siteFixturePath}/config/{$config}.php"));
        }
    }
}
