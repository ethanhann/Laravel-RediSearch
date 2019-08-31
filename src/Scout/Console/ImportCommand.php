<?php

namespace Ehann\LaravelRediSearch\Scout\Console;

use DB;
use Ehann\RediSearch\Index;
use Ehann\RedisRaw\RedisRawClientInterface;
use Illuminate\Console\Command;
use Ehann\RediSearch\Fields\NumericField;
use Ehann\RediSearch\Fields\GeoField;
use Ehann\RediSearch\Fields\TagField;

class ImportCommand extends Command
{
    protected $signature = 'ehann:redisearch:import 
                            {model : The model class to import.} 
                            {chunk-size : Import model chunk size. Default: 1000} 
                            {--recreate-index : Drop the index before importing.}
                            {--no-id : Do not select by "id" primary key.}
                            {--no-import-models : Create index but dont import model.}
                            ';
    protected $description = 'Import models into index';

    public function handle(RedisRawClientInterface $redisClient)
    {
        $class = $this->argument('model');
        $chunk_size = $this->argument('chunk-size') ?? 1000;
        $model = new $class();
        $index = new Index($redisClient, $model->searchableAs());

        $fields = array_keys($model->searchableSchema());
        if (!$this->option('no-id')) {
            $fields[] = $model->getKeyName();
            $query = implode(', ', array_unique($fields));
        }

        if ($this->option('no-id') || $query === '') {
            $query = '*';
        }

        // Define Schema
        foreach ($model->searchableSchema() as $name => $value) {

            if ($name !== $model->getKeyName()) {
                $value = $value ?? '';

                if ($value === NumericField::class) {
                    $index->addNumericField($name);
                    continue;
                }
                if ($value === GeoField::class) {
                    $index->addGeoField($name);
                    continue;
                }
                if ($value === TagField::class) {
                    $index->addTagField($name);
                    continue;
                }

                $index->addTextField($name);
            }
        }

        if ($this->option('recreate-index')) {
            $index->drop();
        }

        if (!$index->create()) {
            $this->warn('The index already exists. Use --recreate-index to recreate the index before importing.');
        }

        if (!$this->option('no-import-models')) {
            $records_total = $class::count();
            if (!$records_total) {
                $this->warn('There are no models to import.');
            }
            $bar = $this->output->createProgressBar($records_total);
            $records = $class::select(DB::raw($query));
            $records
                ->chunk($chunk_size, function ($models) use ($index, $model, $bar) {
                    $documents = [];
                    foreach($models as $model) {
                        $document = $index->makeDocument(
                            $item->getKey()
                        );
                        foreach ($item->toSearchableArray() as $name => $value) {
                            if ($name !== $model->getKeyName()) {
                                $value = $value ?? '';
                                $document->$name->setValue($value);
                            }
                        }
                        $documents[] = $document;
                        $bar->advance();
                    }

                    $index->addMany($documents);
                });
            $bar->finish();

            $this->info("[$class] models imported created successfully");
        } else {
            $this->info("$class index created successfully");
        }
    }
}
