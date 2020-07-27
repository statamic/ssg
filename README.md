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


## Routes

Routes will not automatically be generated. You can add any additional URLs you wish to be generated by adding them to the `urls` array in the config file.

``` php
'urls' => [
    '/this-route',
    '/that-route',
],
```

## Profiles

You may wish to generate specific parts of the site for testing, or to partially sync a large site. In these cases, you may use named profiles to generate a group of pages.

``` php
'profiles' => [
  'news' => [
    '/news-route',
    '/news-route',
  ],
  'about' => [
    '/about/*',
  ],
],
```

You may call the profile with the following command:

```
php please ssg:generate --profile=news
```

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

After your site has an APP_URL...

- Set it as an environment variable. Add `APP_URL` `https://thats-numberwang-47392.netlify.com`

#### S3 Asset Containers

If you are storing your assets in an S3 bucket, the `.env`s used will need to be different to the defaults that come with Laravel, as they are reserved by Netlify. For example, you can amend them to the following:

```sh
# .env
AWS_S3_ACCESS_KEY_ID=
AWS_S3_SECRET_ACCESS_KEY=
AWS_S3_DEFAULT_REGION=
AWS_S3_BUCKET=
AWS_URL=
```

Be sure to also update these in your `s3` disk configuration:

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_S3_ACCESS_KEY_ID'),
    'secret' => env('AWS_S3_SECRET_ACCESS_KEY'),
    'region' => env('AWS_S3_DEFAULT_REGION'),
    'bucket' => env('AWS_S3_BUCKET'),
    'url' => env('AWS_URL'),
],
```

### Deploy to [Surge](https://surge.sh)

**Prerequisite:** Install with `npm install --global surge`. Your first deployment will involve creating an account via command line.

- Build with command `php please ssg:generate`
- Deploy with `surge storage/app/static`

### Deploy to [Firebase hosting](https://firebase.google.com/products/hosting/)

**Prerequisite:** Follow the instructions to [get started with Firebase hosting](https://firebase.google.com/docs/hosting/quickstart)

- Once hosting is set up, make sure the `public` config in your `firebase.json` is set to `storage/app/static`
- (Optionally) Add a `predeploy` config to run `php please ssg:generate`
- Run `firebase deploy`
