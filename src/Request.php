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

    public function getScheme()
    {
        return explode('://', $this->config['base_url'])[0];
    }

    public function getHttpHost()
    {
        $withoutScheme = rtrim(explode('://', $this->config['base_url'])[1], '/');

        $length = strlen($this->getBaseUrl());

        return ($length === 0) ? $withoutScheme : substr($withoutScheme, 0, -$length);
    }

    protected function prepareBaseUrl()
    {
        $withoutScheme = rtrim(explode('://', $this->config['base_url'])[1], '/');

        $base = Arr::get(explode('/', $withoutScheme), 1, '');

        return $base !== '' ? '/'.$base : $base;
    }

    public function getPathInfo()
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
}
