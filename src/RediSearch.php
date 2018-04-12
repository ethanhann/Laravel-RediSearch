<?php

namespace Ehann\LaravelRediSearch;

use Ehann\RediSearch\Index;
use Ehann\RediSearch\Suggestion;

class RediSearch
{
    /** @var ClientAdapter */
    public $redis;

    public function __construct(ClientAdapter $redis = null)
    {
        $this->redis = $redis;
    }

    public function makeDocumentIndex(string $name)
    {
        return new Index($this->redis, $name);
    }

    public function makeSuggestionIndex(string $name)
    {
        return new Suggestion($this->redis, $name);
    }
}
