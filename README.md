# Statamic Static Site Generator

Generate static sites with Statamic 3.

![Statamic 3.0+](https://img.shields.io/badge/Statamic-3.0+-FF269E?style=for-the-badge&link=https://statamic.com)



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

### Multiple Workers

For improved performance, you may spread the page generation across multiple workers. This requires Spatie's [Fork](https://github.com/spatie/fork) package. Then you may specify how many workers are to be used. You can use as many workers as you have CPU cores.

```
composer require spatie/fork
php please ssg:generate --workers=4
```


## Routes

Routes will not automatically be generated. You can add any additional URLs you wish to be generated by adding them to the `urls` array in the config file.

``` php
'urls' => [
    '/this-route',
    '/that-route',
],
```

You can also exclude single routes, or route groups with wildcards. This will override anything in the `urls` config.

``` php
'exclude' => [
    '/secret-page',
    '/cheat-codes/*',
],
```

### Dynamic Routes

You may add URLs dynamically by providing a closure that returns an array to the `addUrls` method.

```php
use Statamic\StaticSite\SSG;

class AppServiceProvider extends Provider
{
    public function boot()
    {
        SSG::addUrls(function () {
            return ['/one', '/two'];
        });
    }
}
```

### Pagination Routes

Wherever pagination is detected in your antlers templates (eg. if you use the `paginate` param on the `collection` tag), multiple pages will automatically be generated with `/articles/page/2` style urls.

You may configure a custom routing style in `config/statamic/ssg.php`:

```php
'pagination_route' => '{url}/{page_name}/{page_number}',
``` 


## Post-generation callback

You may optionally define extra steps to be executed after the site has been generated.

``` php
use Statamic\StaticSite\SSG;

class AppServiceProvider extends Provider
{
    public function boot()
    {
        SSG::after(function () {
            // eg. copy directory to some server
        });
    }
}
```


## Glide Images

The default configuration of Statamic is to have Glide use "dynamic" images, which means that the `glide` tag will only output URLs. The images themselves will be generated when the URLs are visited. For a static site, this no longer makes sense since it will typically be deployed somewhere where there is no dynamic Glide route available.

By default, the SSG will automatically reconfigure Glide to generate images into the `img` directory whenever `glide` tags are used. This is essentially Glide's [custom static path option](https://statamic.dev/image-manipulation#custom-path-static).

You can customize where the images will be generated:

```php
'glide' => [
    'directory' => 'images',
],
```

If you are using a [custom glide disk](https://statamic.dev/image-manipulation#custom-disk-cdn), you can tell the SSG to leave it alone:

```php
'glide' => [
    'override' => false,
],
```

And then copy the images over (or create a symlink) after generating has completed:

```php
SSG::after(function () {
    $from = public_path('img');
    $to = config('statamic.ssg.destination').'/img';

    app('files')->copyDirectory($from, $to);
    // or
    app('files')->link($from, $to);
});
```

## Triggering Command Failures

If you are using the SSG in a CI environment, you may want to prevent the command from succeeding if any pages aren't generated (e.g. to prevent deployment of an incomplete site).

By default, the command will finish and exit with a success code even if there were un-generated pages. You can tell configure the SSG to fail early on errors, or even on warnings.

```php
'failures' => 'errors', // or 'warnings'
```


## Deployment Examples

These examples assume your workflow will be to author content **locally** and _not_ using the control panel in production.

### Deploy to [Netlify](https://netlify.com)

Deployments are triggered by committing to Git and pushing to GitHub.

- Create a site in your [Netlify](https://netlify.com) account
- Link the site to your desired GitHub repository
- Add build command `php please ssg:generate` (if you need to compile css/js, be sure to add that command too and execute it before generating the static site folder. e.g. `npm install && npm run build && php please ssg:generate`).
- Set publish directory `storage/app/static`

After your site has an APP_URL...

- Set it as an environment variable. Add `APP_URL` `https://thats-numberwang-47392.netlify.com`

Finally, generate an `APP_KEY` to your .env file locally using `php artisan key:generate` and copy it's value, then...

- Set it as an environment variable. Add `APP_KEY` `[your app key value]`

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

### Deploy to [Vercel](https://vercel.com)

Deployments are triggered by committing to Git and pushing to GitHub.
- Back in your project, create a new file at root level called `./build.sh`, and paste the code snippet below.
- Run `chmod +x build.sh` on your terminal to make sure the file can be executed when deploying to Vercel.
- Push a commit to your repo including the `build.sh` file.
- Import a new site in your [Vercel](https://vercel.com) account
- Link the site to your desired GitHub repository
- In Project Settings:
  - Set Build Command to `./build.sh`
  - Set Output Directory to `storage/app/static`
  - Set Node.js Version to `18.x`
- In Environment Variables
  - Set `APP_KEY`: `<copy & paste from env or run php artisan key:generate on the terminal to generate a new one>`. Save, edit it and set the value to "Secret".
  - Set `APP_ENV`: `production` **(Optional)** 
  - Set `APP_NAME`: `<copy & paste from env>` **(Optional)**
  - Set `APP_URL`: `<your production url>` **(Optional)** 
#### Code for build.sh
Add the following snippet to `build.sh` file to install PHP, Composer, and run the `ssg:generate` command:

```sh
#!/bin/sh

# Install PHP
yum update
yum install -y amazon-linux-extras
amazon-linux-extras enable php8.2
yum clean metadata
yum install php php-{common,curl,mbstring,gd,gettext,bcmath,json,xml,fpm,intl,zip}

# INSTALL COMPOSER
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
then
    >&2 echo 'ERROR: Invalid installer checksum'
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
rm composer-setup.php

# INSTALL COMPOSER DEPENDENCIES
php composer.phar install

# COMPILE PRODUCTION BUILD
# You can add any compile command here (Yarn, npm, mix...) or remove the command if you don't need to compile anything.
mix --production

# GENERATE STATIC SITE
# Remove --workers flag if spatie/fork is not present in your composer.json.
php please ssg:generate --workers=4
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
