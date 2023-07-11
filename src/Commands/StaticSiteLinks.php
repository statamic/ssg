<?php

namespace Statamic\StaticSite\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\StaticSite\Generator;
use Wilderborn\Partyline\Facade as Partyline;

class StaticSiteLinks extends Command
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
    protected $signature = 'statamic:ssg:links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate symlinks for the static site';

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

        $this->generator->createSymlinks();
    }
}
