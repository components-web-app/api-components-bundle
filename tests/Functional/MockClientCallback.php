<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        return new MockResponse('OK');
    }
}
