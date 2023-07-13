<?php

namespace Statamic\StaticSite;

use Illuminate\Support\Arr;

class Request extends \Illuminate\Http\Request
{
    protected $config;
    protected $page;

    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    public function getScheme(): string
    {
        return explode('://', $this->config['base_url'])[0];
    }

    public function getHttpHost(): string
    {
        $withoutScheme = rtrim(explode('://', $this->config['base_url'])[1], '/');

        $length = strlen($this->getBaseUrl());

        return ($length === 0) ? $withoutScheme : substr($withoutScheme, 0, -$length);
    }

    protected function prepareBaseUrl(): string
    {
        $withoutScheme = rtrim(explode('://', $this->config['base_url'])[1], '/');

        $base = Arr::get(explode('/', $withoutScheme), 1, '');

        return $base !== '' ? '/'.$base : $base;
    }

    public function getPathInfo(): string
    {
        return $this->page->url();
    }

    public function path()
    {
        $path = $this->getPathInfo();

        if ($path === '/') {
            return '/';
        }

        return ltrim($path, '/');
    }

    public function forget(string $inputKey)
    {
        $this->getInputSource()->remove($inputKey);

        return $this;
    }
}
