<?php

namespace Statamic\StaticSite;

use Illuminate\Foundation\Vite as LaravelVite;

class Vite extends LaravelVite
{
    public static function from(LaravelVite $vite): static
    {
        $instance = new static;

        foreach (get_object_vars($vite) as $property => $value) {
            $instance->{$property} = $value;
        }

        return $instance;
    }

    public function isRunningHot(): bool
    {
        return false;
    }
}
