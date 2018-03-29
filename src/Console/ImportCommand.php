<?php

namespace Ehann\Scout\Console;

use DB;
use Ehann\RediSearch\Fields\FieldFactory;
use Ehann\RediSearch\Index;
use Ehann\RediSearch\Redis\RedisClient;
use Illuminate\Console\Command;
use Redis;

class ImportCommand extends Command
{
    protected $signature = 'ehann:redisearch:import 
                            {model : The model class to import.} 
                            {--recreate-index : Drop the index before importing.}
                            {--no-id : Do not select by "id" primary key.}
                            ';
    protected $description = 'Import models into index';

    public function handle(Redis $redis)
    {
        $class = $this->argument('model');
        $model = new $class();
        $index = new Index(
            new RedisClient(
                config('ehann-redisearch.client'),
                config('ehann-redisearch.host'),
                config('ehann-redisearch.port'),
                config('ehann-redisearch.database'),
                config('ehann-redisearch.password')
            ),
            $model->searchableAs()
        );

        $fields = array_keys($model->toSearchableArray());
        if (!$this->option('no-id')) {
            $fields[] = $model->getKeyName();
            $query = implode(', ', array_unique($fields));
        }

        if ($this->option('no-id') || $query === '') {
            $query = '*';
        }
        $records = DB::connection($model->getConnectionName())->table($model->getTable())
            ->select(DB::raw($query))
            ->get();

        // Define Schema
        $records->each(function ($item) use ($index, $model) {
            foreach ($item as $name => $value) {
                if ($name !== $model->getKeyName()) {
                    $value = $value ?? '';
                    $index->$name = FieldFactory::make($name, $value);
                }
            }
        });

        if ($records->isEmpty()) {
            $this->warn('There are no models to import.');
        }

        if ($this->option('recreate-index')) {
            $index->drop();
        }

        if (!$index->create()) {
            $this->warn('The index already exists. Use --recreate-index to recreate the index before importing.');
        }

        $records
            ->each(function ($item) use ($index, $model) {
                $document = $index->makeDocument(
                    property_exists($item, $model->getKeyName()) ? $item->{$model->getKeyName()} : null
                );
                foreach ((array)$item as $name => $value) {
                    if ($name !== $model->getKeyName()) {
                        $value = $value ?? '';
                        $document->$name = FieldFactory::make($name, $value);
                    }
                }
                $index->add($document);
            });

        $this->info('All [' . $class . '] records have been imported.');
    }
}
