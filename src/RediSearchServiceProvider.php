<?php

namespace Ehann\LaravelRediSearch;

use Ehann\LaravelRediSearch\Scout\Console\ImportCommand;
use Ehann\LaravelRediSearch\Scout\Engines\RediSearchEngine;
use Ehann\RediSearch\Redis\RedisClientInterface;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class RediSearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RedisClientInterface::class, function () {
            return new ClientAdapter();
        });
    }

    public function boot()
    {
        $this->app[EngineManager::class]->extend('ehann-redisearch', function ($app) {
            return new RediSearchEngine($app[RedisClientInterface::class]);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);
        }
    }
}
