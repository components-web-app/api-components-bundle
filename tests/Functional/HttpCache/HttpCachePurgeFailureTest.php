<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\HttpCache;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Depends;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\MockClientCallback;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

final class HttpCachePurgeFailureTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use LogRecordsTrait;
    use MercureTrait;
    use ResourceTrait;

    public function test_creating_a_resource_while_the_http_cache_is_unreachable_returns_201_logs_the_failure_and_still_publishes_to_mercure(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        MockClientCallback::setCacheUnreachable(true);

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => 'primary', 'uiComponent' => 'PrimaryLayout']);

        self::assertResponseStatusCodeSame(201);
        $layout = self::jsonNode('@id');
        self::assertResourceExists($layout);
        self::assertLogged(
            'the cache purge failure for ' . $layout,
            static fn (LogRecord $record): bool => self::isCachePurgeFailure($record) && \in_array($layout, $record->context['tags'], true),
        );
        self::assertMercureUpdatePublishedFor($layout);
    }

    #[Depends('test_creating_a_resource_while_the_http_cache_is_unreachable_returns_201_logs_the_failure_and_still_publishes_to_mercure')]
    public function test_the_http_cache_is_reachable_again_in_the_next_test(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => 'secondary', 'uiComponent' => 'PrimaryLayout']);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged(self::jsonNode('@id'));
        self::assertNotLogged('a cache purge failure', self::isCachePurgeFailure(...));
    }

    public function test_purging_the_rendered_html_while_the_http_cache_is_unreachable_is_a_502_not_a_success(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        MockClientCallback::setCacheUnreachable(true);

        self::requestJsonLd($client, 'POST', '/_/rendered_html/purge');

        self::assertResponseStatusCodeSame(502);
        self::assertStringContainsString('application/problem+json', (string) $client->getResponse()?->getHeaders(false)['content-type'][0]);
    }

    private static function isCachePurgeFailure(LogRecord $record): bool
    {
        return Level::Error === $record->level && \is_array($record->context['tags'] ?? null) && ($record->context['exception'] ?? null) instanceof HttpClientExceptionInterface;
    }
}
