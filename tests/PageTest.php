<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Statamic\Contracts\Entries\Entry;
use Statamic\StaticSite\Page;

class PageTest extends TestCase
{
    /** @test */
    public function it_gets_the_path()
    {
        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/foo/bar');

        $page = $this->page($entry, ['destination' => '/path/to/static']);

        $this->assertEquals('/path/to/static/foo/bar/index.html', $page->path());
        $this->assertEquals('/path/to/static/foo/bar', $page->directory());
    }

    /** @test */
    public function it_gets_the_path_of_a_url_with_a_file_extension()
    {
        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/foo/bar/sitemap.xml');

        $page = $this->page($entry, ['destination' => '/path/to/static']);

        $this->assertEquals('/path/to/static/foo/bar/sitemap.xml', $page->path());
        $this->assertEquals('/path/to/static/foo/bar', $page->directory());
    }

    /** @test */
    public function it_gets_the_path_of_the_404_url()
    {
        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/404');

        $page = $this->page($entry, ['destination' => '/path/to/static']);

        $this->assertEquals('/path/to/static/404.html', $page->path());
        $this->assertEquals('/path/to/static', $page->directory());
    }

    private function page($entry, $config)
    {
        return new Page($this->mock(Filesystem::class), $config, $entry);
    }
}
