<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Realtime;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Realtime\RealtimePublishClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RealtimePublishClientTest extends TestCase
{
    private const string JWT = 'header.payload.signature';

    public function test_the_bounded_client_gives_a_publish_through_a_real_hub_one_second_at_most(): void
    {
        $options = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $requestOptions) use (&$options): MockResponse {
            $options = $requestOptions;

            return new MockResponse('urn:uuid:1');
        });

        $id = (new Hub('https://hub.example/.well-known/mercure', new StaticTokenProvider(self::JWT), null, null, RealtimePublishClient::bound($client)))->publish(new Update('https://example.com/_/realtime', '{}', true));

        self::assertSame('urn:uuid:1', $id);
        self::assertSame(1.0, $options['timeout']);
        self::assertSame(1.0, $options['max_duration']);
    }

    public function test_the_bounded_client_never_changes_the_client_it_copies(): void
    {
        $options = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $requestOptions) use (&$options): MockResponse {
            $options = $requestOptions;

            return new MockResponse('');
        });

        RealtimePublishClient::bound($client);
        $client->request('POST', 'https://hub.example/.well-known/mercure');

        self::assertNotSame(1.0, $options['max_duration']);
    }

    public function test_with_no_client_the_bounded_client_is_a_new_one(): void
    {
        self::assertInstanceOf(HttpClientInterface::class, RealtimePublishClient::bound());
    }

    public function test_the_publish_is_bounded_at_one_second(): void
    {
        self::assertSame(1.0, RealtimePublishClient::TIMEOUT_SECONDS);
    }
}
