<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use ApiPlatform\Test\Client;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait ApiRequestTrait
{
    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, string>            $headers
     */
    protected static function requestJsonLd(Client $client, string $method, string $url, array|string|null $body = null, array $headers = []): ResponseInterface
    {
        $options = [
            'headers' => array_merge(
                ['accept' => 'application/ld+json', 'content-type' => static::contentTypeFor($method)],
                array_change_key_case($headers),
            ),
        ];
        if (null !== $body) {
            $options['body'] = \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR);
        }

        return $client->request($method, $url, $options);
    }

    protected static function contentTypeFor(string $method): string
    {
        return 'PATCH' === strtoupper($method) ? 'application/merge-patch+json' : 'application/ld+json';
    }
}
