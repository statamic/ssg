<?php

namespace Tests\Localized;

use Illuminate\Filesystem\Filesystem;
use Statamic\Facades\Config;
use Statamic\Facades\Site;
use Tests\Concerns\RunsGeneratorCommand;
use Tests\TestCase;

class GenerateTest extends TestCase
{
    use RunsGeneratorCommand;

    protected $siteFixture = 'site-localized';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.system.multisite', true);

        Site::setSites([
            'default' => [
                'name' => 'English',
                'locale' => 'en_US',
                'url' => '/',
            ],
            'french' => [
                'name' => 'French',
                'locale' => 'fr_FR',
                'url' => '/fr/',
            ],
            'italian' => [
                'name' => 'Italian',
                'locale' => 'it_IT',
                'url' => '/it/',
            ],
        ]);
    }

    /** @test */
    public function it_generates_pages_for_localized_site_fixture()
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
            'fr/index.html',
            'fr/le-about/index.html',
            'fr/le-topics/index.html',
            'fr/le-articles/index.html',
            'fr/le-articles/le-one/index.html',
            'fr/le-articles/le-two/index.html',
            'fr/le-articles/le-three/index.html',
            'fr/le-articles/le-four/index.html',
            'fr/le-articles/le-five/index.html',
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

        $this->assertStringContainsString('<h1>Page Title: Le Home</h1>', $files['fr/index.html']);
        $this->assertStringContainsString('<h1>Page Title: Le About</h1>', $files['fr/le-about/index.html']);
        $this->assertStringContainsString('<h1>Page Title: Le Topics</h1>', $files['fr/le-topics/index.html']);

        $this->assertStringContainsString('<h1>Articles Index Page Title</h1>', $index = $files['fr/le-articles/index.html']);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/fr/le-articles/le-one">Le One</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/fr/le-articles/le-two">Le Two</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/fr/le-articles/le-three">Le Three</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/fr/le-articles/le-four">Le Four</a>', $index);
        $this->assertStringContainsString('<a href="http://cool-runnings.com/fr/le-articles/le-five">Le Five</a>', $index);

        $this->assertStringContainsString('<h1>Article Title: Le One</h1>', $files['fr/le-articles/le-one/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Le Two</h1>', $files['fr/le-articles/le-two/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Le Three</h1>', $files['fr/le-articles/le-three/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Le Four</h1>', $files['fr/le-articles/le-four/index.html']);
        $this->assertStringContainsString('<h1>Article Title: Le Five</h1>', $files['fr/le-articles/le-five/index.html']);
    }

    /** @test */
    public function it_generates_localized_paginated_pages()
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

        $files = $this->getGeneratedFilesAtPath($this->destinationPath('fr/le-articles'));

        $expectedArticlesFiles = [
            'fr/le-articles/index.html',
            'fr/le-articles/page/1/index.html',
            'fr/le-articles/page/2/index.html',
            'fr/le-articles/le-one/index.html',
            'fr/le-articles/le-two/index.html',
            'fr/le-articles/le-three/index.html',
            'fr/le-articles/le-four/index.html',
            'fr/le-articles/le-five/index.html',
        ];

        $this->assertEqualsCanonicalizing($expectedArticlesFiles, array_keys($files));

        // Index assertions on implicit page 1
        $index = $files['fr/le-articles/index.html'];
        $this->assertStringContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringNotContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Next Link: /fr/le-articles/page/2', $index);

        // Index assertions on explicit page 1
        $index = $files['fr/le-articles/page/1/index.html'];
        $this->assertStringContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringNotContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Next Link: /fr/le-articles/page/2', $index);

        // Index assertions on page 2
        $index = $files['fr/le-articles/page/2/index.html'];
        $this->assertStringNotContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 2', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Prev Link: /fr/le-articles/page/1', $index);
    }

    /** @test */
    public function it_generates_localized_pagination_with_custom_page_name_and_route()
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

        $files = $this->getGeneratedFilesAtPath($this->destinationPath('fr/le-articles'));

        $expectedArticlesFiles = [
            'fr/le-articles/index.html',
            'fr/le-articles/p-1/index.html',
            'fr/le-articles/p-2/index.html',
            'fr/le-articles/le-one/index.html',
            'fr/le-articles/le-two/index.html',
            'fr/le-articles/le-three/index.html',
            'fr/le-articles/le-four/index.html',
            'fr/le-articles/le-five/index.html',
        ];

        $this->assertEqualsCanonicalizing($expectedArticlesFiles, array_keys($files));

        // Index assertions on implicit page 1
        $index = $files['fr/le-articles/index.html'];
        $this->assertStringContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringNotContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Next Link: /fr/le-articles/p-2', $index);

        // Index assertions on explicit page 1
        $index = $files['fr/le-articles/p-1/index.html'];
        $this->assertStringContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringNotContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Next Link: /fr/le-articles/p-2', $index);

        // Index assertions on page 2
        $index = $files['fr/le-articles/p-2/index.html'];
        $this->assertStringNotContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 2', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Prev Link: /fr/le-articles/p-1', $index);
    }

    /** @test */
    public function it_generates_associated_paginated_pages_when_generating_only_localized_urls_with_pagination()
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

        $files = $this->generate(['urls' => ['fr/le-articles']]);

        $expectedArticlesFiles = [
            'fr/le-articles/index.html',
            'fr/le-articles/page/1/index.html',
            'fr/le-articles/page/2/index.html',
        ];

        $this->assertEqualsCanonicalizing($expectedArticlesFiles, array_keys($files));

        // Index assertions on implicit page 1
        $index = $files['fr/le-articles/index.html'];
        $this->assertStringContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringNotContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Next Link: /fr/le-articles/page/2', $index);

        // Index assertions on explicit page 1
        $index = $files['fr/le-articles/page/1/index.html'];
        $this->assertStringContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringNotContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 1', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Next Link: /fr/le-articles/page/2', $index);

        // Index assertions on page 2
        $index = $files['fr/le-articles/page/2/index.html'];
        $this->assertStringNotContainsStrings(['Le One', 'Le Two', 'Le Three'], $index);
        $this->assertStringContainsStrings(['Le Four', 'Le Five'], $index);
        $this->assertStringContainsString('Current Page: 2', $index);
        $this->assertStringContainsString('Total Pages: 2', $index);
        $this->assertStringContainsString('Prev Link: /fr/le-articles/page/1', $index);
    }
}
