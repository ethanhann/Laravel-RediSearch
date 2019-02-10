<?php

namespace Ehann\LaravelRediSearch;

use Ehann\LaravelRediSearch\Scout\Console\ImportCommand;
use Ehann\LaravelRediSearch\Scout\Engines\RediSearchEngine;
use Ehann\RedisRaw\RedisRawClientInterface;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class RediSearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RedisRawClientInterface::class, function ($app) {
            $clientAdapter = new ClientAdapter();
            $clientAdapter->redis = $app->make('redis');
            return $clientAdapter;
        });

        $this->app->singleton(RediSearch::class, function ($app) {
            return new RediSearch($app->make(RedisRawClientInterface::class));
        });
    }

    public function boot()
    {
        $this->app[EngineManager::class]->extend('ehann-redisearch', function ($app) {
            return new RediSearchEngine($app[RedisRawClientInterface::class]);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);
        }
    }
}
