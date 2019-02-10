<?php

namespace Ehann\LaravelRediSearch\Scout\Console;

use DB;
use Ehann\RediSearch\Fields\FieldFactory;
use Ehann\RediSearch\Index;
use Ehann\RedisRaw\RedisRawClientInterface;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'ehann:redisearch:import 
                            {model : The model class to import.} 
                            {--recreate-index : Drop the index before importing.}
                            {--no-id : Do not select by "id" primary key.}
                            ';
    protected $description = 'Import models into index';

    public function handle(RedisRawClientInterface $redisClient)
    {
        $class = $this->argument('model');
        $model = new $class();
        $index = new Index($redisClient, $model->searchableAs());

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
