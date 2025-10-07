<?php

namespace Statamic\StaticSite\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\URL;
use Statamic\StaticSite\GenerationFailedException;
use Statamic\StaticSite\Generator;
use Wilderborn\Partyline\Facade as Partyline;

use function Laravel\Prompts\confirm;

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
        if (config('statamic.ssg.enforce_trailing_slashes')) {
            URL::enforceTrailingSlashes();
        }

        Partyline::bind($this);

        if (config('statamic.editions.pro') && ! config('statamic.system.license_key')) {
            $this->components->error('Statamic Pro is enabled but no site license was found.');
            $this->components->warn('Please set a valid Statamic License Key in your .env file.');
            $confirmationText = 'By continuing you agree that this build is for testing purposes only. Do you wish to continue?';

            if (! $this->option('no-interaction') && ! confirm($confirmationText)) {
                $this->components->error('Static site generation canceled.');

                return 0;
            }
        }

        if (! $workers = $this->option('workers')) {
            $this->components->info('You may be able to speed up site generation significantly by installing spatie/fork and using multiple workers.');
        }

        try {
            $this->generator
                ->workers($workers ?? 1)
                ->disableClear($this->option('disable-clear') ?? false)
                ->generate($this->argument('urls') ?: '*');
        } catch (GenerationFailedException $e) {
            $this->components->error($e->getConsoleMessage());
            $this->components->error('Static site generation failed.');

            return 1;
        }

        return 0;
    }
}
