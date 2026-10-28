<?php

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\RunsGeneratorCommand;

class GenerateGlideDataUrlTest extends TestCase
{
    use RunsGeneratorCommand;

    const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    #[Test]
    public function it_generates_data_urls_by_reading_images_from_the_static_glide_cache()
    {
        $this->files->put(public_path('image.png'), base64_decode(self::ONE_PIXEL_PNG));

        $this->files->put(
            base_path('resources/views/articles/show.antlers.html'),
            '<img src="{{ glide:data_url src="/image.png" width="50" }}">'
        );

        $files = $this->generate();

        $this->assertStringContainsString('src="data:image/png;base64,', $files['articles/one/index.html']);
    }
}
