<?php

namespace Statamic\StaticSite;

class GenerationFailedException extends \Exception
{
    private $consoleMessage;

    public static function withConsoleMessage($message)
    {
        return (new static)->setConsoleMessage($message);
    }

    public function setConsoleMessage($message)
    {
        $this->consoleMessage = $message;

        return $this;
    }

    public function getConsoleMessage()
    {
        return $this->consoleMessage;
    }
}
