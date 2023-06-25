<?php

namespace Statamic\StaticSite;

use Carbon\Carbon;
use Spatie\Fork\Fork;
use Statamic\Statamic;
use Facades\Statamic\View\Cascade;
use Statamic\Facades\URL;
use Statamic\Support\Str;
use Statamic\Facades\Site;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Glide;
use Statamic\Facades\Term;
use League\Flysystem\Adapter\Local;
use Statamic\Imaging\ImageGenerator;
use Illuminate\Filesystem\Filesystem;
use Statamic\Imaging\StaticUrlBuilder;
use Statamic\Contracts\Imaging\UrlBuilder;
use League\Flysystem\Filesystem as Flysystem;
use Wilderborn\Partyline\Facade as Partyline;
use Illuminate\Contracts\Foundation\Application;
use Statamic\Http\Controllers\FrontendController;

class Generator
{
    protected $app;
    protected $files;
    protected $router;
    protected $tasks;
    protected $config;
    protected $request;
    protected $after;
    protected $extraUrls;
    protected $workers = 1;
    protected $taskResults;

    public function __construct(Application $app, Filesystem $files, Router $router, Tasks $tasks)
    {
        $this->app = $app;
        $this->files = $files;
        $this->router = $router;
        $this->tasks = $tasks;
        $this->extraUrls = collect();
        $this->config = $this->initializeConfig();
    }

    private function initializeConfig()
    {
        $config = config('statamic.ssg');

        if (Str::startsWith($config['base_url'], '/')) {
            $config['base_url'] = 'http://localhost'.$config['base_url'];
        }

        return $config;
    }

    public function workers(int $workers)
    {
        $this->workers = $workers;

        return $this;
    }

    public function after($after)
    {
        $this->after = $after;

        return $this;
    }

    public function addUrls($closure)
    {
        $this->extraUrls[] = $closure;
    }

    public function generate($fresh = false)
    {
        $this->checkConcurrencySupport();

        Site::setCurrent(Site::default()->handle());

        if ($fresh) {
            $this->clearDirectory();
        }

        $this
            ->bindGlide()
            ->createContentFiles()
            ->createSymlinks()
            ->copyFiles()
            ->outputSummary();

        if ($this->after) {
            call_user_func($this->after);
        }
    }

    public function bindGlide()
    {
        $override = $this->config['glide']['override'] ?? true;

        if (! $override) {
            return $this;
        }

        Glide::cacheStore()->clear();

        $directory = Arr::get($this->config, 'glide.directory');

        // Determine which adapter to use for Flysystem 1.x or 3.x.
        $localAdapter = class_exists($legacyAdapter = '\League\Flysystem\Adapter\Local')
            ? $legacyAdapter
            : '\League\Flysystem\Local\LocalFilesystemAdapter';

        $this->app['League\Glide\Server']->setCache(
            new Flysystem(new $localAdapter($this->config['destination'] . '/' . $directory))
        );

        $this->app->bind(UrlBuilder::class, function () use ($directory) {
            return new StaticUrlBuilder($this->app[ImageGenerator::class], [
                'route' => URL::tidy($this->config['base_url'] . '/' . $directory)
            ]);
        });

        return $this;
    }

    public function clearDirectory()
    {
        $this->files->deleteDirectory($this->config['destination'], true);

        return $this;
    }

    public function createSymlinks()
    {
        foreach ($this->config['symlinks'] as $source => $dest) {
            $dest = $this->config['destination'] . '/' . $dest;

            if ($this->files->exists($dest)) {
                Partyline::line("Symlink not created. $dest already exists.");
            } else {
                $this->files->link($source, $dest);
                Partyline::line("<info>[✔]</info> $source symlinked to $dest");
            }
        }

        return $this;
    }

    public function copyFiles()
    {
        foreach ($this->config['copy'] ?? [] as $source => $dest) {
            $dest = $this->config['destination'] . '/' . $dest;

            if (is_file($source)) {
                $this->files->copy($source, $dest);
            } else {
                $this->files->copyDirectory($source, $dest);
            }

            Partyline::line("<info>[✔]</info> $source copied to $dest");
        }

        return $this;
    }

    protected function createContentFiles()
    {
        $request = tap(Request::capture(), function ($request) {
            $request->setConfig($this->config);
            $this->app->instance('request', $request);
            Cascade::withRequest($request);
        });

        $pages = $this->gatherContent();

        Partyline::line("Generating {$pages->count()} content files...");

        $closures = $this->makeContentGenerationClosures($pages, $request);

        $results = $this->tasks->run(...$closures);

        if ($this->anyTasksFailed($results)) {
            throw GenerationFailedException::withConsoleMessage("\x1B[1A\x1B[2K");
        }

        $this->taskResults = $this->compileTasksResults($results);

        $this->outputTasksResults();

        return $this;
    }

    protected function anyTasksFailed($results)
    {
        return collect($results)->contains('');
    }

    protected function compileTasksResults(array $results)
    {
        $results = collect($results);

        return [
            'count' => $results->sum('count'),
            'warnings' => $results->flatMap->warnings,
            'errors' => $results->flatMap->errors,
        ];
    }

    protected function gatherContent()
    {
        Partyline::line('Gathering content to be generated...');

        $pages = $this->pages();

        Partyline::line("\x1B[1A\x1B[2K<info>[✔]</info> Gathered content to be generated");

        return $pages;
    }

    protected function pages()
    {
        return collect()
            ->merge($this->routes())
            ->merge($this->urls())
            ->merge($this->entries())
            ->merge($this->terms())
            ->merge($this->scopedTerms())
            ->values()
            ->unique->url()
            ->reject(function ($page) {
                foreach ($this->config['exclude'] as $url) {
                    if (Str::endsWith($url, '*')) {
                        if (Str::is($url, $page->url())) return true;
                    }
                }

                return in_array($page->url(), $this->config['exclude']);
            })->shuffle();
    }

    protected function makeContentGenerationClosures($pages, $request)
    {
        return $pages->split($this->workers)->map(function ($pages) use ($request) {
            return function () use ($pages, $request) {
                $count = 0;
                $warnings = [];
                $errors = [];

                foreach ($pages as $page) {
                    // There is no getter method, so use reflection.
                    $oldCarbonFormat = (new \ReflectionClass(Carbon::class))->getStaticPropertyValue('toStringFormat');

                    if ($this->shouldSetCarbonFormat($page)) {
                        Carbon::setToStringFormat(Statamic::dateFormat());
                    }

                    $this->updateCurrentSite($page->site());

                    $count++;

                    $request->setPage($page);

                    Partyline::line("\x1B[1A\x1B[2KGenerating ".$page->url());

                    try {
                        $generated = $page->generate($request);
                    } catch (NotGeneratedException $e) {
                        if ($this->shouldFail($e)) {
                            throw GenerationFailedException::withConsoleMessage("\x1B[1A\x1B[2K".$e->consoleMessage());
                        }

                        $errors[] = $e->consoleMessage();
                        continue;
                    } finally {
                        Carbon::setToStringFormat($oldCarbonFormat);
                    }

                    if ($generated->hasWarning()) {
                        if ($this->shouldFail($generated)) {
                            throw GenerationFailedException::withConsoleMessage($generated->consoleMessage());
                        }

                        $warnings[] = $generated->consoleMessage();
                    }
                }

                return compact('count', 'warnings', 'errors');
            };
        })->all();
    }

    protected function outputTasksResults()
    {
        $results = $this->taskResults;

        Partyline::line("\x1B[1A\x1B[2K<info>[✔]</info> Generated {$results['count']} content files");

        $results['warnings']->merge($results['errors'])->each(fn ($error) => Partyline::line($error));
    }

    protected function outputSummary()
    {
        Partyline::info('');
        Partyline::info('Static site generated into ' . $this->config['destination']);

        $total = $this->taskResults['count'];

        if ($errors = count($this->taskResults['errors'])) {
            Partyline::warn("[!] {$errors}/{$total} pages not generated");
        }

        if ($warnings = count($this->taskResults['warnings'])) {
            Partyline::warn("[!] {$warnings}/{$total} pages generated with warnings");
        }
    }

    protected function entries()
    {
        return Entry::all()
            ->reject(function ($entry) {
                return is_null($entry->uri());
            })
            ->map(function ($content) {
                return $this->createPage($content);
            })
            ->filter
            ->isGeneratable();
    }

    protected function terms()
    {
        return Term::all()
            ->filter(fn ($term) => view()->exists($term->template()))
            ->map(function ($content) {
                return $this->createPage($content);
            })->filter->isGeneratable();
    }

    protected function scopedTerms()
    {
        return Collection::all()
            ->flatMap(function ($collection) {
                return $this
                    ->getCollectionTerms($collection)
                    ->filter(fn ($term) => view()->exists($term->template()));
            })
            ->map(function ($content) {
                return $this->createPage($content);
            })
            ->filter
            ->isGeneratable();
    }

    protected function getCollectionTerms($collection)
    {
        return $collection
            ->taxonomies()
            ->flatMap(function ($taxonomy) {
                return $taxonomy->queryTerms()->get();
            })
            ->map
            ->collection($collection);
    }

    protected function urls()
    {
        $extra = $this->extraUrls->flatMap(function ($closure) {
            return $closure();
        });

        if (view()->exists('errors.404')) {
            $extra[] = '/404';
        }

        return collect($this->config['urls'] ?? [])->merge($extra)->map(function ($url) {
            $url = URL::tidy(Str::start($url, $this->config['base_url'].'/'));
            return $this->createPage(new Route($url));
        });
    }

    protected function routes()
    {
        return collect($this->router->getRoutes()->getRoutes())->filter(function ($route) {
            return $route->getActionName() === FrontendController::class.'@route'
                && ! Str::contains($route->uri(), '{');
        })->map(function ($route) {
            $url = URL::tidy(Str::start($route->uri(), $this->config['base_url'].'/'));
            return $this->createPage(new StatamicRoute($url));
        });
    }

    protected function createPage($content)
    {
        return new Page($this->files, $this->config, $content);
    }

    protected function updateCurrentSite($site)
    {
        Site::setCurrent($site->handle());
        Cascade::withSite($site);

        // Set the locale for dates, carbon, and for the translator.
        // This is what happens in Statamic's Localize middleware.
        setlocale(LC_TIME, $site->locale());
        app()->setLocale($site->shortLocale());
    }

    protected function checkConcurrencySupport()
    {
        if ($this->workers === 1 || class_exists(Fork::class)) {
            return;
        }

        throw new \RuntimeException('To use multiple workers, you must install PHP 8 and spatie/fork.');
    }

    protected function shouldFail($item)
    {
        $config = $this->config['failures'];

        if ($item instanceof NotGeneratedException) {
            return in_array($config, ['warnings', 'errors']);
        }

        return $config === 'warnings';
    }

    protected function shouldSetCarbonFormat($page)
    {
        $content = $page->content();

        return $content instanceof \Statamic\Contracts\Entries\Entry
            || $content instanceof \Statamic\Contracts\Taxonomies\Term
            || $content instanceof StatamicRoute;
    }
}
