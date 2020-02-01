<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | This informs the generator where the static site will eventually be hosted.
    | For instance, if you are relying on absolute URLs in your app, this one
    | will be used. It should be an absolute URL, eg. "http://my-app.com"
    |
    */

    'base_url' => config('app.url'),

    /*
    |--------------------------------------------------------------------------
    | Destination Directory
    |--------------------------------------------------------------------------
    |
    | This option defines where the static files will be saved.
    |
    */

    'destination' => storage_path('app/static'),

    /*
    |--------------------------------------------------------------------------
    | Files and Symlinks
    |--------------------------------------------------------------------------
    |
    | You are free to define a set of directories to be copied along with the
    | generated HTML files. For example, you may want to link your CSS,
    | JavaScript, static images, and perhaps any uploaded assets.
    | You may choose to symlink rather than copy.
    |
    */

    'copy' => [
        public_path('css') => 'css',
        public_path('js') => 'js',
    ],

    'symlinks' => [
        // public_path('css') => 'css',
        // public_path('js') => 'js',
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional URLs
    |--------------------------------------------------------------------------
    |
    | Here you may define a list of additional URLs to be generated,
    | such as manually created routes.
    |
    */

    'urls' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude URLs
    |--------------------------------------------------------------------------
    |
    | Here you may define a list of URLs that should not be generated.
    |
    */

    'exclude' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Glide
    |--------------------------------------------------------------------------
    |
    | Glide images are dynamically resized server-side when requesting a URL.
    | On a static site, you would just be serving HTML files without PHP.
    | Glide images will be pre-generated into the given directory.
    |
    */

    'glide' => [
        'directory' => 'img',
    ],

];
