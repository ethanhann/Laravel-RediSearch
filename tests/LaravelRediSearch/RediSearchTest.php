<?php

use Ehann\LaravelRediSearch\ClientAdapter;
use Ehann\LaravelRediSearch\RediSearch;
use Mockery as m;

class RediSearchTest extends PHPUnit\Framework\TestCase
{
    /** @var RediSearch */
    protected $subject;

    public function setUp()
    {
        $this->subject = new RediSearch();
    }

    public function tearDown()
    {
        m::close();
    }

    public function testShouldCreateDocumentIndex()
    {
        $expected = 'foo';
        $redis = m::mock(ClientAdapter::class);
        $this->subject->redis = $redis;

        $result = $this->subject->makeDocumentIndex($expected);

        $this->assertEquals($expected, $result->getIndexName());
    }

    public function testShouldCreateSuggestionIndex()
    {
        $expected = 0;
        $redis = m::mock(ClientAdapter::class);
        $redis->shouldReceive('rawCommand')->once()->andReturns(0);
        $this->subject->redis = $redis;

        $result = $this->subject->makeSuggestionIndex('foo');

        $this->assertEquals($expected, $result->length());
    }

    protected function getClientAdapter()
    {
        $clientAdapter = new ClientAdapter();
        $clientAdapter->redis = m::mock(\Ehann\RedisRaw\PhpRedisAdapter::class);
        return $clientAdapter;
    }
}
