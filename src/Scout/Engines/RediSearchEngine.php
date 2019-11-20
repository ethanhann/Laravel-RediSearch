<?php

namespace Ehann\LaravelRediSearch\Scout\Engines;

use Ehann\RediSearch\Exceptions\DocumentAlreadyInIndexException;
use Ehann\RediSearch\Index;
use Ehann\RedisRaw\RedisRawClientInterface;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Ehann\RediSearch\Fields\NumericField;
use Ehann\RediSearch\Fields\GeoField;
use Ehann\RediSearch\Fields\TagField;

class RediSearchEngine extends Engine
{
    /**
     * @var RedisRawClientInterface
     */
    private $redisRawClient;

    /**
     * RediSearchEngine constructor.
     * @param RedisRawClientInterface $redisRawClient
     */
    public function __construct(RedisRawClientInterface $redisRawClient)
    {
        $this->redisRawClient = $redisRawClient;
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function update($models)
    {
        $model = $models->first();
        $index = new Index($this->redisRawClient, $model->first()->searchableAs());

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

        $documents = $models
            ->map(function ($item) use ($index, $model) {
                $document = $index->makeDocument($item->getKey());
                foreach ($item->toSearchableArray() as $name => $value) {
                    if ($name !== $model->getKeyName()) {
                        $value = $value ?? '';
                        $document->$name->setValue($value);
                    }
                }
                return $document;
            })
            ->toArray();

        $index->replaceMany($documents);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function delete($models)
    {
        $index = new Index($this->redisRawClient, $models->first()->searchableAs());
        $models
            ->map(function ($model) {
                return $model->getKey();
            })
            ->values()
            ->each(function ($key) use ($index) {
                $index->delete($key);
            });
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        $index = (new Index($this->redisRawClient, $builder->index ?? $builder->model->searchableAs()));

        if ($builder->callback) {
            $advanced_search = (call_user_func($builder->callback, $index));

            return $advanced_search->search($builder->query);
        }

        return $index
            ->search($builder->query);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  int $perPage
     * @param  int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
		$index = (new Index($this->redisRawClient, $builder->index ?? $builder->model->searchableAs()));

		if ($builder->callback) {
			$advanced_search = (call_user_func($builder->callback, $index));
			return collect(($advanced_search)
				->limit($page, $perPage)
				->search($builder->query));
		}

        return collect(($index)
            ->limit($page, $perPage)
            ->search($builder->query));
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results->getDocuments())->pluck('id')->values();
    }

    public function map(Builder $builder, $results, $model)
    {
        $results = collect($results);

        $count = $results->first();
        if ($count === 0) {
            return Collection::make();
        }
        $documents = $results->last();
        $keys = collect($documents)
            ->pluck('id')
            ->values()
            ->all();
        $models = $model
            ->whereIn($model->getQualifiedKeyName(), $keys)
            ->get()
            ->keyBy($model->getKeyName());

        return Collection::make($documents)
            ->map(function ($hit) use ($model, $models) {
                $key = $hit->id;
                if (isset($models[$key])) {
                    return $models[$key];
                }
            })->filter();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results->getCount();
    }

    public function flush($model)
    {
        $index = new Index($this->redisRawClient, (new $model())->searchableAs());
        $index->drop();
    }
}
