<?php

namespace Statamic\StaticSite;

use Statamic\StaticSite\Generator;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    public function register()
    {
        $this->app->singleton(Generator::class, function ($app) {
            return new Generator($app, $app['files']);
        });
    }

    public function boot()
    {
        $this->publishes([__DIR__.'/../config/ssg.php' => config_path('statamic/ssg.php')]);
        $this->mergeConfigFrom(__DIR__.'/../config/ssg.php', 'statamic.ssg');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\StaticSiteClear::class,
                Commands\StaticSiteGenerate::class,
                Commands\StaticSiteLinks::class,
            ]);
        }
    }
}
