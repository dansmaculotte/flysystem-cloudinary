# flysystem-cloudinary V3

This project is a league/flysystem adapter for Cloudinary, forked from https://github.com/carlosocarvalho/flysystem-cloudinary

Install

```bash
  composer require dansmaculotte/flysystem-cloudinary
```
Example

```php

use DansMaCulotte\Flysystem\Cloudinary\CloudinaryAdapter as Adapter;

$config = [
    'api_key' => ':key',
    'api_secret' => ':secret',
    'cloud_name' => ':name',
];

$container = new Adapter($config);

$filesystem = new League\Flysystem\Filesystem( $container );

```

## List contents and others actions use Filesystem api

```php

$filesystem->listContents()

```

## Run tests

Tests actually use Cloudinary to run. Copy `.env.example` to `.env` and set your api key, secret and cloud name. Then run:

```bas
composer test
```

Alternatively, you can export following variables before running the tests:

```
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=
CLOUDINARY_CLOUD_NAME=
```

### For use in laravel
<a href="https://github.com/carlosocarvalho/laravel-storage-cloudinary"> Access this repository </a>
