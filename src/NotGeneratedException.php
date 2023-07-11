<?php

namespace Statamic\StaticSite;

use Facade\Ignition\Exceptions\ViewException;
use Statamic\Exceptions\NotFoundHttpException as StatamicNotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as SymfonyNotFoundHttpException;

class NotGeneratedException extends \Exception
{
    protected $page;

    public function __construct($page, $previous)
    {
        parent::__construct($previous->getMessage() ?: get_class($previous), 0, $previous);

        $this->page = $page;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function consoleMessage()
    {
        $exception = $this->getPrevious();

        if ($exception instanceof ViewException) {
            $exception = $exception->getPrevious();
        }

        switch (get_class($exception)) {
            case SymfonyNotFoundHttpException::class:
            case StatamicNotFoundHttpException::class:
                $message = 'Resulted in 404';
                break;
            case HttpException::class:
                $message = 'Resulted in '.$exception->getStatusCode();
                break;
            default:
                $message = $this->getMessage();
        }

        return sprintf('%s %s (%s)', '<fg=red>[✘]</>', $this->getPage()->url(), $message);
    }
}
