# Statamic Static Site Generator

Generate static sites with Statamic 3.

![Statamic 3.0+](https://img.shields.io/badge/Statamic-3.0+-FF269E?style=for-the-badge&link=https://statamic.com)



## License

No license is required during the Statamic 3 beta period.


## Installation

Install the package using Composer:

```
composer require statamic/static-site-generator
```

You may also publish the config file into `config/statamic/static_site.php`:

```
php artisan vendor:publish --provider="Statamic\StaticSite\ServiceProvider"
```


## Usage

Run the following command:

```
php please ssg:generate
```

Your site will be generated into a directory which you can deploy however you like. See [Deployment Examples](#deployment-examples) below for inspiration.


## Post-generation callback

You may optionally define extra steps to be executed after the site has been generated.

``` php
use Statamic\StaticSite\Generator;

class AppServiceProvider extends Provider
{
    public function boot()
    {
        Generator::after(function () {
            // eg. copy directory to some server
        });
    }
}
```

## Deployment Examples

### Push to Netlify

This example assumes you will be authoring content locally, committing to Git, and triggering a deployment by pushing to GitHub. No Control Panel usage on production.

- Create a site in your [Netlify](https://netlify.com) account
- Link the site to your GitHub repository
- Add build command `php please ssg:generate`
- Set publish directory `storage/app/static`
- Add environment variable `PHP_VERSION` `7.2`
