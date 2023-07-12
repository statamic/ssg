<?php

namespace Statamic\StaticSite;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Statamic\Facades\Blink;

class Page
{
    protected $files;
    protected $config;
    protected $content;
    protected $paginationPageName;
    protected $paginationCurrentPage;

    public function __construct(Filesystem $files, array $config, $content)
    {
        $this->files = $files;
        $this->config = $config;
        $this->content = $content;
    }

    public function content()
    {
        return $this->content;
    }

    public function isGeneratable()
    {
        return $this->content->published();
    }

    public function generate($request)
    {
        try {
            $generatedPage = $this->write($request);
        } catch (Exception $e) {
            throw new NotGeneratedException($this, $e);
        }

        if ($paginator = $this->detectPaginator($request)) {
            $this->writePaginatedPages($request, $paginator);
        }

        return $generatedPage;
    }

    protected function write($request)
    {
        if ($this->paginationPageName) {
            $request->merge([$this->paginationPageName => $this->paginationCurrentPage]);
        }

        try {
            $response = $this->content->toResponse($request);
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            throw_unless($response instanceof RedirectResponse, $e);
        }

        $html = $response->getContent();

        if (! $this->files->exists($this->directory())) {
            $this->files->makeDirectory($this->directory(), 0755, true);
        }

        $this->files->put($this->path(), $html);

        return new GeneratedPage($this, $response);
    }

    protected function writePaginatedPages($request, $paginator)
    {
        collect(range(1, $paginator->lastPage()))->each(function ($pageNumber) use ($request) {
            $page = clone $this;

            try {
                $page
                    ->setPaginationCurrentPage($pageNumber)
                    ->write($request);
            } catch (Exception $e) {
                throw new NotGeneratedException($page, $e);
            }
        });

        $this->clearPaginator($request);
    }

    public function directory()
    {
        return dirname($this->path());
    }

    public function path()
    {
        if ($this->is404()) {
            return $this->config['destination'].'/404.html';
        }

        $url = $this->url();

        $ext = pathinfo($url, PATHINFO_EXTENSION) ?: 'html';

        $url = $this->config['destination'].$url;

        if ($ext === 'html') {
            $url .= '/index.html';
        }

        return $url;
    }

    public function url()
    {
        $url = $this->content->urlWithoutRedirect();

        if ($this->paginationCurrentPage) {
            $url = LengthAwarePaginator::generatePaginatedUrl(
                $url,
                $this->paginationPageName,
                $this->paginationCurrentPage
            );
        }

        return $url;
    }

    public function site()
    {
        return $this->content->site();
    }

    public function is404()
    {
        return $this->url() === '/404';
    }

    public function setPaginationCurrentPage($currentPage)
    {
        $this->paginationCurrentPage = $currentPage;

        return $this;
    }

    protected function detectPaginator($request)
    {
        if ($paginator = Blink::get('tag-paginator')) {
            $this->paginationPageName = $paginator->getPageName();
        }

        $this->clearPaginator($request);

        return $paginator;
    }

    protected function clearPaginator($request)
    {
        Blink::forget('tag-paginator');

        if ($this->paginationPageName) {
            $request->forget($this->paginationPageName);
        }
    }
}
