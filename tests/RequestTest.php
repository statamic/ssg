<?php

namespace Tests;

use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\StaticSite\Page;
use Statamic\StaticSite\Request;

class RequestTest extends TestCase
{
    /** @test */
    public function gets_scheme()
    {
        $this->assertEquals('http', (new Request)->setConfig(['base_url' => 'http://test.com'])->getScheme());
        $this->assertEquals('https', (new Request)->setConfig(['base_url' => 'https://test.com'])->getScheme());
    }

    /** @test */
    public function gets_http_host()
    {
        $this->assertEquals('test.com', (new Request)->setConfig(['base_url' => 'http://test.com'])->getHttpHost());
        $this->assertEquals('test.com', (new Request)->setConfig(['base_url' => 'http://test.com/'])->getHttpHost());
        $this->assertEquals('test.com', (new Request)->setConfig(['base_url' => 'https://test.com/subdirectory'])->getHttpHost());
        $this->assertEquals('test.com', (new Request)->setConfig(['base_url' => 'https://test.com/subdirectory/'])->getHttpHost());
    }

    /** @test */
    public function gets_base_url()
    {
        $this->assertEquals('', (new Request)->setConfig(['base_url' => 'http://test.com'])->getBaseUrl());
        $this->assertEquals('', (new Request)->setConfig(['base_url' => 'http://test.com/'])->getBaseUrl());
        $this->assertEquals('/subdirectory', (new Request)->setConfig(['base_url' => 'http://test.com/subdirectory'])->getBaseUrl());
        $this->assertEquals('/subdirectory', (new Request)->setConfig(['base_url' => 'http://test.com/subdirectory/'])->getBaseUrl());
    }

    /** @test */
    public function gets_path()
    {
        // The current site needs to be explicitly set, otherwise it will try to
        // resolve it from the request, which will result in an infinite loop.
        Site::setCurrent(Site::default()->handle());

        Collection::make('test')->routes('{slug}')->save();
        $entry = Entry::make()->slug('foo')->locale('default')->collection('test');
        $page = new Page(app('files'), [], $entry);

        $request = (new Request)->setConfig(['base_url' => 'http://test.com'])->setPage($page);

        // The request needs to be replaced with ours, so that any
        // request operations go through this custom instance.
        $this->app->instance('request', $request);

        $this->assertEquals('foo', $request->path());
    }

    /** @test */
    public function it_can_forget_query_param()
    {
        $request = new Request;

        $request->merge(['page' => 2]);

        $this->assertEquals(['page' => 2], $request->all());

        $request->forget('page');

        $this->assertEquals([], $request->all());
    }
}
