<?php

namespace Statamic\StaticSite\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\StaticSite\GenerationFailedException;
use Statamic\StaticSite\Generator;
use Wilderborn\Partyline\Facade as Partyline;

class StaticSiteGenerate extends Command
{
    use RunsInPlease;

    /**
     * @var Generator
     */
    protected $generator;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:ssg:generate
        {urls?* : You may provide one or more explicit url arguments, otherwise whole site will be generated }
        {--workers= : Speed up site generation significantly by installing spatie/fork and using multiple workers }
        {--disable-clear : Disable clearing the destination directory when generating whole site }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a static site';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Generator $generator)
    {
        parent::__construct();

        $this->generator = $generator;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Partyline::bind($this);

        if (config('statamic.editions.pro') && ! config('statamic.system.license_key')) {
            $this->error('Statamic Pro is enabled but no site license was found.');
            $this->warn('Please set a valid Statamic License Key in your .env file.');
            $confirmationText = 'By continuing you agree that this build is for testing purposes only. Do you wish to continue?';

            if (! $this->option('no-interaction') && ! $this->confirm($confirmationText)) {
                $this->line('Static site generation canceled.');

                return 0;
            }
        }

        if (! $workers = $this->option('workers')) {
            $this->comment('You may be able to speed up site generation significantly by installing spatie/fork and using multiple workers (requires PHP 8+).');
        }

        try {
            $this->generator
                ->workers($workers ?? 1)
                ->disableClear($this->option('disable-clear') ?? false)
                ->generate($this->argument('urls') ?: '*');
        } catch (GenerationFailedException $e) {
            $this->line($e->getConsoleMessage());
            $this->error('Static site generation failed.');

            return 1;
        }

        return 0;
    }
}
