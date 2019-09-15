<?php

namespace Statamic\StaticSite;

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
}
