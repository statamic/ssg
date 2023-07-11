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

        $url = self::generatePaginatedUrl($this->path(), $page);

        if (Str::contains($this->path(), '?') || count($this->query)) {
            $url .= '?'.Arr::query($this->query);
        }

        return $url.$this->buildFragment();
    }

    public static function generatePaginatedUrl($url, $pageNumber)
    {
        $route = config('statamic.ssg.pagination_route');

        $url = str_replace('{url}', $url, $route);

        // TODO: handle $this->pageName?

        $url = str_replace('{number}', $pageNumber, $url);

        return URL::makeRelative($url);
    }
}
