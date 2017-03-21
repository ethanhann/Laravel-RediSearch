<?php

namespace Ehann\Scout\Console;

use DB;
use Ehann\RediSearch\Fields\FieldFactory;
use Ehann\RediSearch\Index;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'ehann:redisearch:import 
                            {model : The model class to import.} 
                            {--recreate-index : Drop the index before importing.}
                            ';
    protected $description = 'Import models into index';

    public function handle()
    {
        $class = $this->argument('model');
        $model = new $class();
        $index = (new Index())->setIndexName($model->searchableAs());
        $fields = array_keys($model->toSearchableArray());
        $fields[] = $model->getKeyName();
        $query = implode(', ', array_unique($fields));
        if ($query === '') {
            $query = '*';
        }

        $records = DB::table($model->getTable())
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
                $document = $index->makeDocument($item->{$model->getKeyName()});
                foreach ((array)$item as $name => $value) {
                    if ($name !== $model->getKeyName()) {
                        $value = $value ?? '';
                        $document->$name = FieldFactory::make($name, $value);
                    }
                }
                $index->add($document);
            });

        $this->info('All ['.$class.'] records have been imported.');
    }
}
