<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * This has been created to give responses to varnish PURGE requests.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class MockClientCallback
{
    private static bool $cacheUnreachable = false;

    public static function setCacheUnreachable(bool $cacheUnreachable): void
    {
        self::$cacheUnreachable = $cacheUnreachable;
    }

    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        if (self::$cacheUnreachable && 'PURGE' === $method) {
            return new MockResponse('', ['error' => \sprintf('Could not resolve host: %s', parse_url($url, \PHP_URL_HOST))]);
        }

        return new MockResponse('OK');
    }
}
