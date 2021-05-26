<?php

namespace Statamic\StaticSite\Commands;

use Illuminate\Console\Command;
use Statamic\StaticSite\Generator;
use Statamic\Console\RunsInPlease;
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
    protected $signature = 'statamic:ssg:generate {--workers=} {--recent} {--since=}';

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

        $recent = $this->option('recent');
        $since = $this->option('since');

        if (! $workers = $this->option('workers')) {
            $this->comment('You may be able to speed up site generation significantly by installing spatie/fork and using multiple workers (requires PHP 8+).');
        }

        $this->generator
            ->workers($workers ?? 1)
            ->generate($recent, $since);
    }
}
