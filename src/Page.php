<?php

namespace Statamic\StaticSite;

use Exception;
use Illuminate\Filesystem\Filesystem;

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

    public function isGeneratable()
    {
        return $this->content->published();
    }

    public function generate($request)
    {
        try {
            $this->write($request);
        } catch (Exception $e) {
            throw new NotGeneratedException($this, $e);
        }
    }

    protected function write($request)
    {
        $html = $this->content->toResponse($request)->getContent();

        if (! $this->files->exists($this->directory())) {
            $this->files->makeDirectory($this->directory(), 0755, true);
        }

        $this->files->put($this->path(), $html);
    }

    public function directory()
    {
        return $this->config['destination'] . $this->url();
    }

    public function path()
    {
        return $this->directory()  . '/index.html';
    }

    public function url()
    {
        return $this->content->url();
    }
}
