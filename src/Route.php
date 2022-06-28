<?php

namespace Statamic\StaticSite;

use Illuminate\Contracts\Http\Kernel;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

class Route
{
    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function url()
    {
        return URL::makeRelative($this->url);
    }

    public function urlWithoutRedirect()
    {
        return $this->url();
    }

    public function site()
    {
        return Site::findByUrl($this->url) ?? Site::default();
    }

    public function toResponse($request)
    {
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        if ($e = $response->exception) {
            if ($response->status() === 404 && $this->url() === '/404') {
                return $response;
            }

            throw $e;
        }

        return $response;
    }
}
