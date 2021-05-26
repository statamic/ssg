<?php

namespace Statamic\StaticSite;

use Spatie\Fork\Fork;
use Facades\Statamic\View\Cascade;
use Statamic\Facades\URL;
use Statamic\Support\Str;
use Statamic\Facades\Site;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
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
    protected $recent;
    protected $since;
    protected $after;
    protected $count = 0;
    protected $skips = 0;
    protected $warnings = 0;
    protected $viewPaths;
    protected $extraUrls;
    protected $workers = 1;

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

    public function generate($recent, $since)
    {
        $this->checkConcurrencySupport();

        Site::setCurrent(Site::default()->handle());

        $this
            ->setRecent($recent, $since)
            ->bindGlide()
            ->backupViewPaths()
            ->clearDirectory()
            ->createContentFiles()
            ->createSymlinks()
            ->copyFiles();

        Partyline::info('Static site generated into ' . $this->config['destination']);

        if ($this->skips) {
            Partyline::warn("[!] {$this->skips}/{$this->count} pages not generated");
        }

        if ($this->warnings) {
            Partyline::warn("[!] {$this->warnings}/{$this->count} pages generated with warnings");
        }

        if ($this->after) {
            call_user_func($this->after);
        }
    }

    public function setRecent($recent, $since)
    {
        $this->recent = $recent;

        if ($recent) {
            $diff = ($since === null) ? '24 hours' : $since;
            Partyline::info("Generating collections updated in the last $diff");

            $this->since = now()->sub($diff)->unix();
        }

        return $this;
    }

    public function bindGlide()
    {
        $directory = Arr::get($this->config, 'glide.directory');

        $this->app['League\Glide\Server']->setCache(
            new Flysystem(new Local($this->config['destination'] . '/' . $directory))
        );

        $this->app->bind(UrlBuilder::class, function () use ($directory) {
            return new StaticUrlBuilder($this->app[ImageGenerator::class], [
                'route' => URL::tidy($this->config['base_url'] . '/' . $directory)
            ]);
        });

        return $this;
    }

    public function backupViewPaths()
    {
        $this->viewPaths = view()->getFinder()->getPaths();

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
    }

    protected function createContentFiles()
    {
        $request = tap(Request::capture(), function ($request) {
            $request->setConfig($this->config);
            $this->app->instance('request', $request);
        });

        $pages = $this->gatherContent();

        Partyline::line("Generating {$pages->count()} content files...");

        $closures = $this->makeContentGenerationClosures($pages, $request);

        $results = $this->tasks->run(...$closures);

        $this->outputResults($results);

        return $this;
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
                $count = $skips = $warnings = 0;
                $errors = [];

                foreach ($pages as $page) {
                    $this->updateCurrentSite($page->site());

                    view()->getFinder()->setPaths($this->viewPaths);

                    $count++;

                    $request->setPage($page);

                    Partyline::line("\x1B[1A\x1B[2KGenerating ".$page->url());

                    try {
                        $generated = $page->generate($request);
                    } catch (NotGeneratedException $e) {
                        $skips++;
                        $errors[] = $e->consoleMessage();
                        continue;
                    }

                    if ($generated->hasWarning()) {
                        $warnings++;
                    }
                }

                return compact('count', 'skips', 'warnings', 'errors');
            };
        })->all();
    }

    protected function outputResults($results)
    {
        $results = collect($results);

        Partyline::line("\x1B[1A\x1B[2K<info>[✔]</info> Generated {$results->sum('count')} content files");

        if ($results->sum('skips')) {
            $results->reduce(function ($carry, $item) {
                return $carry->merge($item['errors']);
            }, collect())->each(function ($error) {
                Partyline::line($error);
            });
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
            ->filter->isGeneratable()
            ->filter->isRecent($this->recent, $this->since);
    }

    protected function terms()
    {
        return Term::all()->map(function ($content) {
            return $this->createPage($content);
        })->filter->isGeneratable();
    }

    protected function scopedTerms()
    {
        return Collection::all()
            ->flatMap(function ($collection) {
                return $this->getCollectionTerms($collection);
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
            return $this->createPage(new Route($url));
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
}
