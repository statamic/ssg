<?php

namespace Statamic\StaticSite;

use Statamic\Routing\Router;
use Statamic\StaticSite\Generator;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    public function register()
    {
        $this->app->singleton(Generator::class, function ($app) {
            return new Generator($app, $app['files'], $app[Router::class]);
        });
    }

    public function boot()
    {
        $this->publishes([__DIR__.'/../config/static_site.php' => config_path('statamic/static_site.php')]);
        $this->mergeConfigFrom(__DIR__.'/../config/static_site.php', 'statamic.static_site');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\StaticSiteClear::class,
                Commands\StaticSiteGenerate::class,
                Commands\StaticSiteLinks::class,
            ]);
        }
    }
}
