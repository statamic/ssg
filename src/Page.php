<?php

namespace Statamic\StaticSite;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

class Page
{
    protected $files;
    protected $config;
    protected $content;

    public function __construct(Filesystem $files, array $config, $content)
    {
        $this->files = $files;
        $this->config = $config;
        $this->content = $content;
    }

    public function content()
    {
        return $this->content;
    }

    public function isGeneratable()
    {
        return $this->content->published();
    }

    public function generate($request)
    {
        try {
            return $this->write($request);
        } catch (Exception $e) {
            throw new NotGeneratedException($this, $e);
        }
    }

    protected function write($request)
    {
        try {
            $response = $this->content->toResponse($request);
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            throw_unless($response instanceof RedirectResponse, $e);
        }

        $html = $response->getContent();

        if (! $this->files->exists($this->directory())) {
            $this->files->makeDirectory($this->directory(), 0755, true);
        }

        $this->files->put($this->path(), $html);

        return new GeneratedPage($this, $response);
    }

    public function directory()
    {
        return dirname($this->path());
    }

    public function path()
    {
        if ($this->is404()) {
            return $this->config['destination'].'/404.html';
        }

        $url = $this->url();

        $ext = pathinfo($url, PATHINFO_EXTENSION) ?: 'html';

        $url = $this->config['destination'].$url;

        if ($ext === 'html') {
            $url .= '/index.html';
        }

        return $url;
    }

    public function url()
    {
        return $this->content->urlWithoutRedirect();
    }

    public function site()
    {
        return $this->content->site();
    }

    public function is404()
    {
        return $this->url() === '/404';
    }
}
