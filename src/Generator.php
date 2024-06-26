<?php

namespace Statamic\StaticSite;

use Carbon\Carbon;
use Facades\Statamic\View\Cascade;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Statamic\Contracts\Imaging\UrlBuilder;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Glide;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Http\Controllers\FrontendController;
use Statamic\Imaging\ImageGenerator;
use Statamic\Imaging\StaticUrlBuilder;
use Statamic\Statamic;
use Statamic\Support\Str;
use Wilderborn\Partyline\Facade as Partyline;

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
    protected $earlyTaskErrors = [];
    protected $taskResults;
    protected $disableClear = false;

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

    public function disableClear(bool $disableClear = false)
    {
        $this->disableClear = $disableClear;

        return $this;
    }

    public function generate($urls = '*')
    {
        $this->checkConcurrencySupport();

        Site::setCurrent(Site::default()->handle());

        if (is_array($urls)) {
            $this->disableClear = true;
        }

        $this
            ->bindGlide()
            ->clearDirectory()
            ->createContentFiles($urls)
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

        $this->app['League\Glide\Server']->setCache(
            new Flysystem(new LocalFilesystemAdapter($this->config['destination'].'/'.$directory))
        );

        $this->app->bind(UrlBuilder::class, function () use ($directory) {
            return new StaticUrlBuilder($this->app[ImageGenerator::class], [
                'route' => URL::tidy($this->config['base_url'].'/'.$directory),
            ]);
        });

        return $this;
    }

    public function clearDirectory()
    {
        if ($this->disableClear) {
            return $this;
        }

        $this->files->deleteDirectory($this->config['destination'], true);

        return $this;
    }

    public function createSymlinks()
    {
        foreach ($this->config['symlinks'] as $source => $dest) {
            $dest = $this->config['destination'].'/'.$dest;

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
            $dest = $this->config['destination'].'/'.$dest;

            if (is_file($source)) {
                $this->files->copy($source, $dest);
            } else {
                $this->files->copyDirectory($source, $dest);
            }

            Partyline::line("<info>[✔]</info> $source copied to $dest");
        }

        return $this;
    }

    protected function createContentFiles($urls = '*')
    {
        $request = tap(Request::capture(), function ($request) {
            $request->setConfig($this->config);
            $this->app->instance('request', $request);
            Cascade::withRequest($request);
        });

        $pages = $this->gatherContent($urls);

        if (app('fork-installed') && $this->workers > 1) {
            $pages = $pages->shuffle();
        }

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
            'count' => count($this->earlyTaskErrors) + $results->sum('count'),
            'warnings' => $results->flatMap->warnings,
            'errors' => collect($this->earlyTaskErrors)->merge($results->flatMap->errors),
        ];
    }

    protected function gatherContent($urls = '*')
    {
        if (is_array($urls)) {
            return collect($urls)
                ->map(fn ($url) => $this->createPage(new Route($this->makeAbsoluteUrl($url))))
                ->reject(fn ($page) => $this->shouldRejectPage($page, true));
        }

        Partyline::line('Gathering content to be generated...');

        $pages = $this->gatherAllPages();

        Partyline::line("\x1B[1A\x1B[2K<info>[✔]</info> Gathered content to be generated");

        return $pages;
    }

    protected function gatherAllPages()
    {
        return collect()
            ->merge($this->routes())
            ->merge($this->urls())
            ->merge($this->entries())
            ->merge($this->terms())
            ->merge($this->scopedTerms())
            ->values()
            ->unique
            ->url()
            ->reject(fn ($page) => $this->shouldRejectPage($page));
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

                    Blink::flush();
                }

                return compact('count', 'warnings', 'errors');
            };
        })->all();
    }

    protected function outputTasksResults()
    {
        $results = $this->taskResults;

        $successCount = $results['count'] - $results['errors']->count();

        Partyline::line("\x1B[1A\x1B[2K<info>[✔]</info> Generated {$successCount} content files");

        $results['warnings']->merge($results['errors'])->each(fn ($error) => Partyline::line($error));
    }

    protected function outputSummary()
    {
        Partyline::info('');
        Partyline::info('Static site generated into '.$this->config['destination']);

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

        return collect($this->config['urls'] ?? [])
            ->merge($extra)
            ->map(fn ($url) => $this->createPage(new Route($this->makeAbsoluteUrl($url))));
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
        if ($this->workers === 1 || app('fork-installed')) {
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

    protected function makeAbsoluteUrl($url)
    {
        return URL::tidy(Str::start($url, $this->config['base_url'].'/'));
    }

    protected function shouldRejectPage($page, $outputError = false)
    {
        foreach ($this->config['exclude'] as $url) {
            if (Str::endsWith($url, '*')) {
                if (Str::is($url, $page->url())) {
                    return true;
                }
            }
        }

        $excluded = in_array($page->url(), $this->config['exclude']);

        if ($excluded && $outputError) {
            $this->earlyTaskErrors[] = '<fg=red>[✘]</> '.URL::makeRelative($page->url()).' (Excluded in config/statamic/ssg.php)';
        }

        return $excluded;
    }
}
