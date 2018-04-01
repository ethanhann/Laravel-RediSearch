<?php

namespace Ehann\LaravelRediSearch;

use Ehann\LaravelRediSearch\Scout\Console\ImportCommand;
use Ehann\LaravelRediSearch\Scout\Engines\RediSearchEngine;
use Ehann\RediSearch\Redis\RedisClient;
use Config;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class RediSearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RedisClient::class, function () {
            return new RedisClient(
                Config::get('ehann-redisearch.client'),
                Config::get('ehann-redisearch.host'),
                Config::get('ehann-redisearch.port'),
                Config::get('ehann-redisearch.database'),
                Config::get('ehann-redisearch.password')
            );
        });
    }

    public function boot()
    {
        $this->app[EngineManager::class]->extend('ehann-redisearch', function ($app) {
            return new RediSearchEngine($app[RedisClient::class]);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);
            $this->publishes([
                __DIR__ . '/../config/ehann-redisearch.php' => $this->app['path.config'] . DIRECTORY_SEPARATOR . 'ehann-redisearch.php',
            ]);
        }
    }
}
