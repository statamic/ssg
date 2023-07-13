<?php

namespace Statamic\StaticSite;

use Statamic\Extensions\Pagination\LengthAwarePaginator as StatamicLengthAwarePaginator;
use Statamic\Facades\URL;
use Statamic\Support\Arr;
use Statamic\Support\Str;

class LengthAwarePaginator extends StatamicLengthAwarePaginator
{
    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $url = self::generatePaginatedUrl($this->path(), $this->getPageName(), $page);

        if (Str::contains($this->path(), '?') || count($this->query)) {
            $url .= '?'.Arr::query($this->query);
        }

        return $url.$this->buildFragment();
    }

    public static function generatePaginatedUrl($url, $pageName, $pageNumber)
    {
        $route = config('statamic.ssg.pagination_route');

        $url = str_replace('{url}', $url, $route);

        $url = str_replace('{page_name}', $pageName, $url);

        $url = str_replace('{page_number}', $pageNumber, $url);

        return URL::makeRelative($url);
    }
}
