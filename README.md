# Statamic Static Site Generator

Generate static sites with Statamic 3.

![Statamic 3.0+](https://img.shields.io/badge/Statamic-3.0+-FF269E?style=for-the-badge&link=https://statamic.com)



## License

No license is required during the Statamic 3 beta period.


## Installation

Install the package using Composer:

```
composer require statamic/ssg
```

If you want or need to customize the way the site is generated, you can do so by publishing and modifying the config file with the following command: 

```
php artisan vendor:publish --provider="Statamic\StaticSite\ServiceProvider"
```

The config file will be in `config/statamic/ssg.php`. This is optional and you can do it anytime.


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

These examples assumes your workflow will be to author content **locally** and _not_ using the control panel in production.

### Deploy to [Netlify](https://netlify.com)

Deployments are triggered by committing to Git and pushing to GitHub.

- Create a site in your [Netlify](https://netlify.com) account
- Link the site to your GitHub repository
- Add build command `php please ssg:generate`
- Set publish directory `storage/app/static`
- Add environment variable: `PHP_VERSION` `7.2`

After your site has an APP_URL, set it as an environment variable: `APP_URL` `https://thats-numberwang-47392.netlify.com`

### Deploy to [Surge](https://surge.sh)

**Prerequisite:** Install with `npm install --global surge`. Your first deployment will involve creating an account via command line.

- Build with command `php please ssg:generate`
- Deploy with `surge storage/app/static`

### Deploy to [Firebase hosting](https://firebase.google.com/products/hosting/)

**Prerequisite:** Follow the instructions to [get started with Firebase hosting](https://firebase.google.com/docs/hosting/quickstart)

- Once hosting is set up, make sure the `public` config in your `firebase.json` is set to `storage/app/static`
- (Optionally) Add a `predeploy` config to run `php please ssg:generate`
- Run `firebase deploy`
