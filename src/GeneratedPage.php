<?php

namespace Statamic\StaticSite;

class GeneratedPage
{
    protected $page;
    protected $response;

    public function __construct($page, $response)
    {
        $this->page = $page;
        $this->response = $response;
    }

    public function url()
    {
        return $this->page->url();
    }

    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    public function hasWarning()
    {
        return $this->isRedirect();
    }

    public function isRedirect()
    {
        return in_array($this->getStatusCode(), [301, 302]);
    }

    public function consoleMessage()
    {
        $message = vsprintf('%s%s %s', [
            "\x1B[1A\x1B[2K",
            $this->hasWarning() ? '<comment>[!]</comment>' : '<info>[✔]</info>',
            $this->url(),
        ]);

        if ($this->isRedirect()) {
            $message .= sprintf(' (Resulted in a redirect to %s)', $this->response->getTargetUrl());
        }

        return $message;
    }
}
