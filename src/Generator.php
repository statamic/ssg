<?php

namespace Statamic\StaticSite;

use Statamic\Facades\URL;
use Statamic\Support\Str;
use Statamic\Facades\Site;
use Illuminate\Support\Arr;
use Statamic\Facades\Entry;
use League\Flysystem\Adapter\Local;
use Statamic\Imaging\ImageGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Statamic\Imaging\StaticUrlBuilder;
use Statamic\Contracts\Imaging\UrlBuilder;
use League\Flysystem\Filesystem as Flysystem;
use Facade\Ignition\Exceptions\ViewException;
use Wilderborn\Partyline\Facade as Partyline;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Exceptions\HttpResponseException;
use Statamic\Exceptions\NotFoundHttpException as StatamicNotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as SymfonyNotFoundHttpException;

class Generator
{
    protected $app;
    protected $files;
    protected $config;
    protected $request;
    protected $after;
    protected $count = 0;
    protected $skips = 0;
    protected $viewPaths;

    public function __construct(Application $app, Filesystem $files)
    {
        $this->app = $app;
        $this->files = $files;
        $this->config = config('statamic.ssg');
    }

    public function after($after)
    {
        $this->after = $after;

        return $this;
    }

    public function generate()
    {
        Site::setCurrent(Site::default()->handle());

        $this
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

        if ($this->after) {
            call_user_func($this->after);
        }
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
                Partyline::line("$source symlinked to $dest");
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

            Partyline::line("$source copied to to $dest");
        }
    }

    protected function createContentFiles()
    {
        $request = tap(Request::capture(), function ($request) {
            $request->setConfig($this->config);
            $this->app->instance('request', $request);
        });

        $this->pages()->each(function ($page) use ($request) {
            view()->getFinder()->setPaths($this->viewPaths);

            $this->count++;

            $request->setPage($page);

            Partyline::comment("Generating {$page->url()}...");

            try {
                $page->generate($request);
                Partyline::line(sprintf('%s%s %s', "\x1B[1A\x1B[2K", '<info>[✔]</info>', $page->url()));
            } catch (NotGeneratedException $e) {
                $this->skips++;
                Partyline::line($this->notGeneratedMessage($e));
            }
        });

        return $this;
    }

    protected function pages()
    {
        return collect()
            ->merge($this->urls())
            ->merge($this->content())
            ->values()
            ->reject(function ($page) {
                return in_array($page->url(), $this->config['exclude']);
            })->sortBy(function ($page) {
                return str_replace('/', '', $page->url());
            });
    }

    protected function content()
    {
        return Entry::all()->map(function ($content) {
            return $this->createPage($content);
        })->filter->isGeneratable();
    }

    protected function urls()
    {
        return collect($this->config['urls'] ?? [])->map(function ($url) {
            $url = Str::start($url, '/');
            return $this->createPage(new Route($url));
        });
    }

    protected function createPage($content)
    {
        return new Page($this->files, $this->config, $content);
    }

    protected function notGeneratedMessage($e)
    {
        $previous = $e->getPrevious();

        if ($previous instanceof ViewException) {
            $previous = $previous->getPrevious();
        }

        switch (get_class($previous)) {
            case SymfonyNotFoundHttpException::class:
            case StatamicNotFoundHttpException::class:
                $message = 'Resulted in 404';
                break;
            case HttpResponseException::class:
                if (($response = $previous->getResponse()) instanceof RedirectResponse) {
                    $message = sprintf('Resulted in a %s redirect to %s', $response->getStatusCode(), $response->getTargetUrl());
                    break;
                }
            default:
                $message = $e->getMessage();
        }

        return sprintf('%s %s (%s)', "\x1B[1A\x1B[2K<fg=red>[✘]</>", $e->getPage()->url(), $message);
    }
}
