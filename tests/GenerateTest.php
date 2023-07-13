<?php

namespace Tests;

use Statamic\Facades\Config;
use Statamic\Facades\Path;
use Statamic\StaticSite\ConsecutiveTasks;
use Statamic\StaticSite\Tasks;

class GenerateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force this test to use ConsecutiveTasks implementation.
        // Because spatie/fork was mocked in an earlier test case,
        // it can cause the suite to fail when it gets to this test.
        $this->app->bind(Tasks::class, fn () => new ConsecutiveTasks);

        $this->destination = storage_path('app/static');

        $this->cleanUpDestination();
    }

    public function tearDown(): void
    {
        $this->cleanUpDestination();

        parent::tearDown();
    }

    /** @test */
    public function it_generates_pages_for_site_fixture()
    {
        $files = $this->generate();

        $expectedFiles = [
            '404.html',
            'index.html',
            'about/index.html',
            'topics/index.html',
            'articles/index.html',
            'articles/one/index.html',
            'articles/two/index.html',
            'articles/three/index.html',
            'articles/four/index.html',
            'articles/five/index.html',
            'articles/six/index.html',
            'articles/seven/index.html',
            'articles/eight/index.html',
        ];

        $this->assertEqualsCanonicalizing($expectedFiles, array_keys($files));

        $this->assertStringContainsString('<h1>404!</h1>', $files['404.html']);

        $this->assertStringContainsString('<h1>Page Title: Home</h1>', $files['index.html']);
        $this->assertStringContainsString('<h1>Page Title: About</h1>', $files['about/index.html']);
        $this->assertStringContainsString('<h1>Page Title: Topics</h1>', $files['topics/index.html']);

        $this->assertStringContainsString('<h1>Articles Index Page Title</h1>', $index = $files['articles/index.html']);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/one">One</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/two">Two</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/three">Three</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/four">Four</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/five">Five</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/six">Six</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/seven">Seven</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/articles/eight">Eight</a>', $index);

        $this->assertStringContainsString('<h1>Article Title: One</h1>', $files['articles/one/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Two</h1>', $files['articles/two/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Three</h1>', $files['articles/three/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Four</h1>', $files['articles/four/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Five</h1>', $files['articles/five/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Six</h1>', $files['articles/six/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Seven</h1>', $files['articles/seven/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Eight</h1>', $files['articles/eight/index.html']);
    }

    /** @test */
    public function it_generates_pages_to_custom_destination()
    {
        Config::set('statamic.ssg.destination', $this->destination = base_path('custom_export'));

        $this->generate();

        $this->assertFalse($this->files->exists(storage_path('app/static')));
        $this->assertCount(13, $this->files->allFiles(base_path('custom_export')));

        $this->cleanUpDestination();
    }

    /** @test */
    public function it_generates_paginated_pages()
    {
        $this->files->put(resource_path('views/articles/index.antlers.html'), <<<'EOT'
{{ collection:articles sort="date:asc" paginate="3" as="articles" }}
    {{ articles }}
        <a href="{{ permalink }}">{{ title }}</a>
    {{ /articles }}

    {{ paginate }}
        Current Page: {{ current_page }}
        Total Pages: {{ total_pages }}
        Prev Link: {{ prev_page }}
        Next Link: {{ next_page }}
    {{ /paginate }}
{{ /collection:articles }}
EOT
        );

        $this->generate();

        $files = $this->getGeneratedFilesAtPath($this->destinationPath('articles'));

        $expectedArticlesFiles = [
            'articles/index.html',
            'articles/page/1/index.html',
            'articles/page/2/index.html',
            'articles/page/3/index.html',
            'articles/one/index.html',
            'articles/two/index.html',
            'articles/three/index.html',
            'articles/four/index.html',
            'articles/five/index.html',
            'articles/six/index.html',
            'articles/seven/index.html',
            'articles/eight/index.html',
        ];

        $this->assertEqualsCanonicalizing($expectedArticlesFiles, array_keys($files));

        // Index assertions on implicit page 1
        $index = $files['articles/index.html'];
        $this->assertStringContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringNotContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringNotContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Next Link: /articles/page/2', $index);

        // Index assertions on explicit page 1
        $index = $files['articles/page/1/index.html'];
        $this->assertStringContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringNotContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringNotContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Next Link: /articles/page/2', $index);

        // Index assertions on page 2
        $index = $files['articles/page/2/index.html'];
        $this->assertStringNotContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringNotContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 2', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Prev Link: /articles/page/1', $index);
        $this->assertStringContainsString('Next Link: /articles/page/3', $index);

        // Index assertions on page 3
        $index = $files['articles/page/3/index.html'];
        $this->assertStringNotContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringNotContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 3', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Prev Link: /articles/page/2', $index);
    }

    private function generate()
    {
        $this->assertFalse($this->files->exists($this->destination));

        $this
            ->artisan('statamic:ssg:generate')
            ->doesntExpectOutputToContain('pages not generated');

        $this->assertTrue($this->files->exists($this->destination));

        return $this->getGeneratedFilesAtPath($this->destination);
    }

    private function relativePath($path)
    {
        return str_replace(Path::tidy($this->destination.'/'), '', Path::tidy($path));
    }

    private function destinationPath($path)
    {
        return Path::tidy($this->destination.'/'.$path);
    }

    private function getGeneratedFilesAtPath($path)
    {
        return collect($this->files->allFiles($path))
            ->mapWithKeys(fn ($file) => [$this->relativePath($file->getPathname()) => $file->getContents()])
            ->all();
    }

    private function cleanUpDestination($destination = null)
    {
        $destination ??= $this->destination;

        if ($this->files->exists($destination)) {
            $this->files->deleteDirectory($destination);
        }
    }

    private function assertStringContainsStrings($needleStrings, $haystackString)
    {
        foreach ($needleStrings as $string) {
            $this->assertStringContainsString($string, $haystackString);
        }
    }

    private function assertStringNotContainsStrings($needleStrings, $haystackString)
    {
        foreach ($needleStrings as $string) {
            $this->assertStringNotContainsString($string, $haystackString);
        }
    }
}
