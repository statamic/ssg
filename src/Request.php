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
        return parse_url($this->config['base_url'])['scheme'];
    }

    public function getHttpHost()
    {
        $host = parse_url($this->config['base_url'])['host'];

        $length = strlen($this->getBaseUrl());

        return ($length === 0) ? $host : substr($host, 0, -$length);
    }

    protected function prepareBaseUrl()
    {
        $host = parse_url($this->config['base_url'])['host'];

        $base = Arr::get(explode('/', $host), 1, '');

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
