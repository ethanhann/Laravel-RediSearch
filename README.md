# Laravel-RediSearch

An experimental [Laravel Scout](https://laravel.com/docs/5.6/scout) driver for [RediSearch](http://redisearch.io) that uses [RediSearch-PHP](https://github.com/ethanhann/redisearch-php) under the hood.

Register the provider in config/app.php

```php

'providers' => [
// ...
    
    Ehann\Scout\RediSearchScoutServiceProvider::class
    
// ...
],

```

To customize the configure, publish the configuration file

```bash
php artisan vendor:publish --provider="Ehann\Scout\RediSearchScoutServiceProvider"
```
