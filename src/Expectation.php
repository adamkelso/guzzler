<?php

namespace Guzzler;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\Invocation\ObjectInvocation;
use PHPUnit\Framework\MockObject\Matcher\InvokedRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Class Expectation
 * @package Guzzler
 * @method $this get(string $uri)
 * @method $this post(string $uri)
 * @method $this put(string $uri)
 * @method $this delete(string $uri)
 * @method $this patch(string $uri)
 * @method $this options(string $uri)
 */
class Expectation
{
    use Filters;

    /** @var Guzzler */
    protected $guzzler;

    protected $filters = [];

    /** @var InvokedRecorder */
    protected $times;

    /**
     * Each value in this array becomes a convenience method over endpoint().
     */
    public const VERBS = [
        'get', 'post', 'put', 'delete', 'patch', 'options'
    ];

    /**
     * Expectation constructor.
     * @param null|InvokedRecorder $times
     * @param null|Guzzler $guzzler
     */
    public function __construct($times = null, $guzzler = null)
    {
        $this->times = $times;
        $this->guzzler = $guzzler;
        $this->endpoint = new Uri();
    }

    protected function addFilter($filter)
    {
        if (!in_array($filter, $this->filters)) {
            $this->filters[] = $filter;
        }
    }

    public function endpoint(string $uri, string $method)
    {
        $this->endpoint = Uri::fromParts(parse_url($uri));
        $this->method = $method;

        $this->addFilter('endpoint');

        return $this;
    }

    /**
     * This is used exclusively for the convenience verb methods.
     *
     * @param string $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if (!in_array($name, self::VERBS)) {
            throw new \Error(sprintf("Call to undefined method %s::%s()",__CLASS__, $name));
        }

        return $this->endpoint($arguments[0], strtoupper($name));
    }

    public function withHeader(string $key, $value)
    {
        $this->headers[$key] = $value;

        $this->addFilter('headers');

        return $this;
    }

    public function withHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->withHeader($key, $value);
        }

        return $this;
    }

    public function withOption(string $key, $value)
    {
        $this->options[$key] = $value;

        $this->addFilter('options');

        return $this;
    }

    public function withOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $this->withOption($key, $value);
        }

        return $this;
    }

    public function withBody(string $body)
    {
        $this->body = $body;

        $this->addFilter('body');

        return $this;
    }

    public function withVersion($protocol)
    {
        $this->protocol = $protocol;

        $this->addFilter('protocol');

        return $this;
    }

    /**
     * Set a follow through; either response, callable, or Exception.
     *
     * @param $response
     * @param int $times
     * @return $this
     */
    public function will($response, int $times = 1)
    {
        for ($i = 0; $i < $times; $i++) {
            $this->guzzler->queueResponse($response);
        }

        return $this;
    }

    /**
     * An alias of 'will'.
     *
     * @param $response
     * @param int $times
     * @return $this
     */
    public function willRespond($response, int $times = 1)
    {
        $this->will($response, $times);

        return $this;
    }

    protected function runFilters(array $history)
    {
        foreach ($this->filters as $filter) {
            $history = $this->{'filterBy' . ucfirst($filter)}($history);
        }

        return $history;
    }

    /**
     * Iterate over the history and verify the invocations against it.
     *
     * @param TestCase $instance
     * @param array $history
     */
    public function __invoke(TestCase $instance, array $history): void
    {
        foreach ($this->runFilters($history) as $i) {
            $this->times->invoked(new ObjectInvocation('', '', [], '', $i['request']));
        }

        try {
            // Invocation Counts
            $this->times->verify();
        } catch (ExpectationFailedException $e) {
            Assert::fail($e->getMessage().' '.$this->__toString());
        }
    }

    public function __toString()
    {
        $headers = json_encode($this->headers, JSON_PRETTY_PRINT);
        $options = json_encode($this->options, JSON_PRETTY_PRINT);
        $query = json_encode($this->query, JSON_PRETTY_PRINT);

        return <<<MESSAGE


Expectation: {$this->endpoint}
-----------------------------
  Method:   {$this->method}
  Headers:  {$headers}
  Options:  {$options}
  Query:    {$query}
  Protocol: {$this->protocol}
  Body:     {$this->body}
MESSAGE;
    }
}