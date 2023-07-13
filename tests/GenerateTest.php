<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Statamic\Facades\Config;
use Tests\Concerns\RunsGeneratorCommand;

class GenerateTest extends TestCase
{
    use RunsGeneratorCommand;

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
    public function it_generates_specific_pages_when_passing_urls_as_args()
    {
        $this
            ->partialMock(Filesystem::class)
            ->shouldReceive('deleteDirectory')
            ->with(config('statamic.ssg.destination'), true)
            ->never();

        $files = $this->generate(['urls' => ['/', 'topics', 'articles']]);

        $expectedFiles = [
            'index.html',
            'topics/index.html',
            'articles/index.html',
        ];

        $this->assertEqualsCanonicalizing($expectedFiles, array_keys($files));

        $this->assertStringContainsString('<h1>Page Title: Home</h1>', $files['index.html']);
        $this->assertStringContainsString('<h1>Page Title: Topics</h1>', $files['topics/index.html']);
        $this->assertStringContainsString('<h1>Articles Index Page Title</h1>', $files['articles/index.html']);
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
    public function it_clears_destination_directory_when_generating_site()
    {
        $this
            ->partialMock(Filesystem::class)
            ->shouldReceive('deleteDirectory')
            ->with(config('statamic.ssg.destination'), true)
            ->once();

        $this->generate();
    }

    /** @test */
    public function it_can_generate_site_without_clearing_destination_directory()
    {
        $this
            ->partialMock(Filesystem::class)
            ->shouldReceive('deleteDirectory')
            ->with(config('statamic.ssg.destination'), true)
            ->never();

        $this->generate(['--disable-clear' => true]);
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

    /** @test */
    public function it_generates_pagination_with_custom_page_name_and_route()
    {
        // Here we'll override the `pagination_route`.
        Config::set('statamic.ssg.pagination_route', '{url}/{page_name}-{page_number}');

        // And we'll also use a custom `page_name` param.
        // This could even be passed in as a translatable string if the user wants `page` in different languages, etc.
        $this->files->put(resource_path('views/articles/index.antlers.html'), <<<'EOT'
{{ collection:articles sort="date:asc" paginate="3" page_name="p" as="articles" }}
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
            'articles/p-1/index.html',
            'articles/p-2/index.html',
            'articles/p-3/index.html',
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
        $this->assertStringContainsString('Next Link: /articles/p-2', $index);

        // Index assertions on explicit page 1
        $index = $files['articles/p-1/index.html'];
        $this->assertStringContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringNotContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringNotContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Next Link: /articles/p-2', $index);

        // Index assertions on page 2
        $index = $files['articles/p-2/index.html'];
        $this->assertStringNotContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringNotContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 2', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Prev Link: /articles/p-1', $index);
        $this->assertStringContainsString('Next Link: /articles/p-3', $index);

        // Index assertions on page 3
        $index = $files['articles/p-3/index.html'];
        $this->assertStringNotContainsStrings(['One', 'Two', 'Three'], $index);
        $this->assertStringNotContainsStrings(['Four', 'Five', 'Six'], $index);
        $this->assertStringContainsStrings(['Seven', 'Eight'], $index);
        $this->assertStringContainsString('Current Page: 3', $index);
        $this->assertStringContainsString('Total Pages: 3', $index);
        $this->assertStringContainsString('Prev Link: /articles/p-2', $index);
    }

    /** @test */
    public function it_generates_associated_paginated_pages_when_generating_only_urls_with_pagination()
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

        $this
            ->partialMock(Filesystem::class)
            ->shouldReceive('deleteDirectory')
            ->with(config('statamic.ssg.destination'), true)
            ->never();

        $files = $this->generate(['urls' => ['articles']]);

        $expectedArticlesFiles = [
            'articles/index.html',
            'articles/page/1/index.html',
            'articles/page/2/index.html',
            'articles/page/3/index.html',
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
}
