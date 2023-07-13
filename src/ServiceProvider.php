<?php

namespace Statamic\StaticSite;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Spatie\Fork\Fork;
use Statamic\Extensions\Pagination\LengthAwarePaginator as StatamicLengthAwarePaginator;

class ServiceProvider extends LaravelServiceProvider
{
    public function register()
    {
        $this->app->bind('fork-installed', fn () => class_exists(Fork::class));

        $this->app->bind(Tasks::class, function ($app) {
            return $app->runningInConsole() && $app['fork-installed']
                ? new ConcurrentTasks(new Fork)
                : new ConsecutiveTasks;
        });

        $this->app->singleton(Generator::class, function ($app) {
            return new Generator($app, $app[Filesystem::class], $app[Router::class], $app[Tasks::class]);
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
                Commands\StaticSiteServe::class,
            ]);
        }

        if ($this->app->runningInConsole()) {
            $this->app->extend(StatamicLengthAwarePaginator::class, function ($paginator) {
                return $this->app->makeWith(LengthAwarePaginator::class, [
                    'items' => $paginator->getCollection(),
                    'total' => $paginator->total(),
                    'perPage' => $paginator->perPage(),
                    'currentPage' => $paginator->currentPage(),
                    'options' => $paginator->getOptions(),
                ]);
            });
        }
    }
}
