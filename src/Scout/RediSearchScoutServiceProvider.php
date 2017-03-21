<?php

namespace Ehann\Scout;

use Ehann\Scout\Console\ImportCommand;
use Ehann\Scout\Engines\RediSearchEngine;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class RediSearchScoutServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app[EngineManager::class]->extend('ehann-redisearch', function ($app) {
            return new RediSearchEngine();
        });
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);
        }
    }
}
