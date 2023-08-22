<?php

namespace Statamic\StaticSite\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Env;
use Statamic\Console\RunsInPlease;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Termwind\terminal;

class StaticSiteServe extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:ssg:serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the static site on the PHP development server';

    /**
     * The list of requests being handled and their start time.
     *
     * @var array<int, \Illuminate\Support\Carbon>
     */
    protected $requestsPool;

    /**
     * Indicates if the "Server running on..." output message has been displayed.
     *
     * @var bool
     */
    protected $serverRunningHasBeenDisplayed = false;

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws \Exception
     */
    public function handle()
    {
        $process = $this->startProcess();

        while ($process->isRunning()) {
            usleep(500 * 1000);
        }

        $status = $process->getExitCode();

        return $status;
    }

    /**
     * Start a new server process.
     *
     * @param  bool  $hasEnvironment
     * @return \Symfony\Component\Process\Process
     */
    protected function startProcess()
    {
        $process = new Process($this->serverCommand(), config('statamic.ssg.destination'));

        $process->start($this->handleProcessOutput());

        return $process;
    }

    /**
     * Get the full server command.
     *
     * @return array
     */
    protected function serverCommand()
    {
        $server = __DIR__.'/../resources/server.php';

        return [
            (new PhpExecutableFinder)->find(false),
            '-S',
            $this->host().':'.$this->port(),
            $server,
        ];
    }

    /**
     * Get the host for the command.
     *
     * @return string
     */
    protected function host()
    {
        [$host] = $this->getHostAndPort();

        return $host;
    }

    /**
     * Get the port for the command.
     *
     * @return string
     */
    protected function port()
    {
        $port = $this->input->getOption('port');

        if (is_null($port)) {
            [, $port] = $this->getHostAndPort();
        }

        $port = $port ?: 8000;

        return $port;
    }

    /**
     * Get the host and port from the host option string.
     *
     * @return array
     */
    protected function getHostAndPort()
    {
        $hostParts = explode(':', $this->input->getOption('host'));

        return [
            $hostParts[0],
            $hostParts[1] ?? null,
        ];
    }

    /**
     * Returns a "callable" to handle the process output.
     *
     * @return callable(string, string): void
     */
    protected function handleProcessOutput()
    {
        return fn ($type, $buffer) => str($buffer)->explode("\n")->each(function ($line) {
            if (str($line)->contains('Development Server (http')) {
                if ($this->serverRunningHasBeenDisplayed) {
                    return;
                }

                $this->components->info("Server running on [http://{$this->host()}:{$this->port()}].");
                $this->comment('  <fg=yellow;options=bold>Press Ctrl+C to stop the server</>');

                $this->newLine();

                $this->serverRunningHasBeenDisplayed = true;
            } elseif (str($line)->contains(' Accepted')) {
                $requestPort = $this->getRequestPortFromLine($line);

                $this->requestsPool[$requestPort] = [
                    $this->getDateFromLine($line),
                    false,
                ];
            } elseif (str($line)->contains([' [200]: GET '])) {
                $requestPort = $this->getRequestPortFromLine($line);

                $this->requestsPool[$requestPort][1] = trim(explode('[200]: GET', $line)[1]);
            } elseif (str($line)->contains(' Closing')) {
                $requestPort = $this->getRequestPortFromLine($line);
                $request = $this->requestsPool[$requestPort];

                [$startDate, $file] = $request;

                $formattedStartedAt = $startDate->format('Y-m-d H:i:s');

                unset($this->requestsPool[$requestPort]);

                [$date, $time] = explode(' ', $formattedStartedAt);

                $this->output->write("  <fg=gray>$date</> $time");

                $runTime = $this->getDateFromLine($line)->diffInSeconds($startDate);

                if ($file) {
                    $this->output->write($file = " $file");
                }

                $dots = max(terminal()->width() - mb_strlen($formattedStartedAt) - mb_strlen($file) - mb_strlen($runTime) - 9, 0);

                $this->output->write(' '.str_repeat('<fg=gray>.</>', $dots));
                $this->output->writeln(" <fg=gray>~ {$runTime}s</>");
            } elseif (str($line)->contains(['Closed without sending a request'])) {
                // ...
            } elseif (! empty($line)) {
                $warning = explode('] ', $line);
                $this->components->warn(count($warning) > 1 ? $warning[1] : $warning[0]);
            }
        });
    }

    /**
     * Get the date from the given PHP server output.
     *
     * @param  string  $line
     * @return \Illuminate\Support\Carbon
     */
    protected function getDateFromLine($line)
    {
        $regex = env('PHP_CLI_SERVER_WORKERS', 1) > 1
            ? '/^\[\d+]\s\[(.*)]/'
            : '/^\[([^\]]+)\]/';

        preg_match($regex, $line, $matches);

        return Carbon::createFromFormat('D M d H:i:s Y', $matches[1]);
    }

    /**
     * Get the request port from the given PHP server output.
     *
     * @param  string  $line
     * @return int
     */
    protected function getRequestPortFromLine($line)
    {
        preg_match('/:(\d+)\s(?:(?:\w+$)|(?:\[.*))/', $line, $matches);

        return (int) $matches[1];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', Env::get('SERVER_HOST', '127.0.0.1')],
            ['port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', Env::get('SERVER_PORT')],
        ];
    }
}
