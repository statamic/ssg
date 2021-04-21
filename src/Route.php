<?php

namespace Statamic\StaticSite;

use Illuminate\Contracts\Http\Kernel;
use Statamic\Facades\Site;

class Route
{
    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function url()
    {
        return $this->url;
    }

    public function site()
    {
        return Site::findByUrl($this->url());
    }

    public function toResponse($request)
    {
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        if ($e = $response->exception) {
            throw $e;
        }

        return $response;
    }
}
