<?php

namespace Tests\Concerns;

use Statamic\Facades\Path;

trait RunsGeneratorCommand
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->destination = storage_path('app/static');

        $this->cleanUpDestination();
    }

    public function tearDown(): void
    {
        $this->cleanUpDestination();

        parent::tearDown();
    }

    protected function generate($options = [])
    {
        $this->assertFalse($this->files->exists($this->destination));

        $options['--no-interaction'] ??= true;

        $this
            ->artisan('statamic:ssg:generate', $options)
            ->doesntExpectOutputToContain('pages not generated');

        $this->assertTrue($this->files->exists($this->destination));

        return $this->getGeneratedFilesAtPath($this->destination);
    }

    protected function relativePath($path)
    {
        return str_replace(Path::tidy($this->destination.'/'), '', Path::tidy($path));
    }

    protected function destinationPath($path)
    {
        return Path::tidy($this->destination.'/'.$path);
    }

    protected function getGeneratedFilesAtPath($path)
    {
        return collect($this->files->allFiles($path))
            ->mapWithKeys(fn ($file) => [$this->relativePath($file->getPathname()) => $file->getContents()])
            ->all();
    }

    protected function cleanUpDestination($destination = null)
    {
        $destination ??= $this->destination;

        if ($this->files->exists($destination)) {
            $this->files->deleteDirectory($destination);
        }
    }

    protected function assertStringContainsStrings($needleStrings, $haystackString)
    {
        foreach ($needleStrings as $string) {
            $this->assertStringContainsString($string, $haystackString);
        }
    }

    protected function assertStringNotContainsStrings($needleStrings, $haystackString)
    {
        foreach ($needleStrings as $string) {
            $this->assertStringNotContainsString($string, $haystackString);
        }
    }
}
