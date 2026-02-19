<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Customized Guzzle client that is more compatible with GAE's URL Fetch
 *
 * @mixin Client
 */
class HttpClient implements ClientInterface
{
    private static bool $turnOffVerify = true;
    private static bool $removeHost = false;
    private ClientInterface $client;

    public function __construct(array $config = [])
    {
        /**
         * The default Guzzle behavior adds cafile and allow_self_signed
         * options to the stream handler which causes URL Fetch to throw
         * an error. Since URL Fetch will always verify SSL, we can safely
         * set verify to false.
         */
        if (self::$turnOffVerify) {
            $config['verify'] = false;
        }

        /**
         * Create a new handler stack to remove the Host header
         * (URL Fetch ignores this header and throws a warning)
         */
        if (self::$removeHost) {
            $stack = new HandlerStack();
            $stack->setHandler(new StreamHandler());
            $stack->push($this->removeHeader('Host'));
            $config['handler'] = $stack;
        }

        $this->client = new Client($config);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->client->{$name}(...$arguments);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->client->send($request, $options);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->client->sendAsync($request, $options);
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }

    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        return $this->client->requestAsync($method, $uri, $options);
    }

    public function getConfig(?string $option = null)
    {
        return $this->client->getConfig($option);
    }

    protected function removeHeader(string $header): callable
    {
        return function (callable $handler) use ($header) {
            return function (
                RequestInterface $request,
                array $options
            ) use ($handler, $header) {
                $request = $request->withoutHeader($header);
                return $handler($request, $options);
            };
        };
    }
}
