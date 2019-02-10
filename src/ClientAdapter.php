<?php

namespace Ehann\LaravelRediSearch;

use Ehann\RedisRaw\AbstractRedisRawClient;
use Exception;
use Illuminate\Support\Facades\Redis;

class ClientAdapter extends AbstractRedisRawClient
{
    /** @var Redis */
    public $redis;

    public function multi(bool $usePipeline = false)
    {
        return $usePipeline ? $this->redis->pipeline() : $this->redis->multi();
    }

    public function rawCommand(string $command, array $arguments)
    {
        $arguments = $this->prepareRawCommandArguments($command, $arguments);
        $rawResult = null;
        try {
            $rawResult = $this->redis->executeRaw($arguments);
        } catch (Exception $exception) {
            $this->validateRawCommandResults($exception);
        }
        return $this->normalizeRawCommandResult($rawResult);
    }
}