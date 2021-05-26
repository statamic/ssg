<?php

namespace Statamic\StaticSite;

use Spatie\Fork\Fork;
use Statamic\StaticSite\Generator;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    public function register()
    {
        $this->app->bind(Tasks::class, function ($app) {
            return $app->runningInConsole() && class_exists(Fork::class)
                ? new ConcurrentTasks(new Fork)
                : new ConsecutiveTasks;
        });

        $this->app->singleton(Generator::class, function ($app) {
            return new Generator($app, $app['files'], $app['router'], $app[Tasks::class]);
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
