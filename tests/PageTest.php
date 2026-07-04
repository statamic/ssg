<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Entries\Entry;
use Statamic\StaticSite\Page;

class PageTest extends TestCase
{
    #[Test]
    public function it_gets_the_path()
    {
        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/foo/bar');

        $page = $this->page($entry, ['destination' => '/path/to/static']);

        $this->assertEquals('/path/to/static/foo/bar/index.html', $page->path());
        $this->assertEquals('/path/to/static/foo/bar', $page->directory());
    }

    #[Test]
    public function it_gets_the_path_of_a_url_with_a_file_extension()
    {
        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/foo/bar/sitemap.xml');

        $page = $this->page($entry, ['destination' => '/path/to/static']);

        $this->assertEquals('/path/to/static/foo/bar/sitemap.xml', $page->path());
        $this->assertEquals('/path/to/static/foo/bar', $page->directory());
    }

    #[Test]
    public function it_gets_the_path_of_the_404_url()
    {
        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/404');

        $page = $this->page($entry, ['destination' => '/path/to/static']);

        $this->assertEquals('/path/to/static/404.html', $page->path());
        $this->assertEquals('/path/to/static', $page->directory());
    }

    #[Test]
    public function it_writes_when_another_worker_creates_the_directory_after_the_existence_check()
    {
        // Simulate the race between parallel workers, where the directory doesn't
        // exist when checked but has appeared by the time mkdir runs.
        $files = new class extends Filesystem
        {
            public function exists($path)
            {
                return false;
            }
        };

        $destination = base_path('static');
        $files->deleteDirectory($destination);
        $files->makeDirectory($destination.'/foo/bar', 0755, true);

        $entry = $this->mock(Entry::class);
        $entry->shouldReceive('urlWithoutRedirect')->andReturn('/foo/bar');
        $entry->shouldReceive('toResponse')->andReturn(new Response('html'));

        $page = new Page($files, ['destination' => $destination], $entry);

        $page->generate(Request::create('/foo/bar'));

        $this->assertFileExists($destination.'/foo/bar/index.html');
    }

    private function page($entry, $config)
    {
        return new Page($this->mock(Filesystem::class), $config, $entry);
    }
}
