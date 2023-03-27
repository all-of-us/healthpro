<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;

/**
 * Customized Guzzle client that is more compatible with GAE's URL Fetch
 */
class HttpClient extends Client
{
    private static $turnOffVerify = true;
    private static $removeHost = false;

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

        parent::__construct($config);
    }

    protected function removeHeader($header)
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
