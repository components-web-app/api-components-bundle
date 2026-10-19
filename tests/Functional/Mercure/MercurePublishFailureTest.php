<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Mercure;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Depends;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Mercure\Exception\InvalidArgumentException as MercureInvalidArgumentException;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

final class MercurePublishFailureTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use LogRecordsTrait;
    use ResourceTrait;

    public function test_creating_a_resource_while_the_mercure_hub_is_unreachable_returns_201_and_logs_the_failure(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        HubStub::setUnreachable(true);

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => 'primary', 'uiComponent' => 'PrimaryLayout']);

        self::assertResponseStatusCodeSame(201);
        $layout = self::jsonNode('@id');
        self::assertResourceExists($layout);
        self::assertLogged(
            'the Mercure publish failure for ' . $layout,
            static fn (LogRecord $record): bool => self::isMercurePublishFailure($record)
                && Level::Error === $record->level
                && \is_string($record->context['resource'] ?? null)
                && str_ends_with($record->context['resource'], $layout)
                && \in_array($record->context['resource'], $record->context['topics'] ?? [], true),
        );
    }

    #[Depends('test_creating_a_resource_while_the_mercure_hub_is_unreachable_returns_201_and_logs_the_failure')]
    public function test_the_mercure_hub_is_reachable_again_in_the_next_test(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => 'secondary', 'uiComponent' => 'PrimaryLayout']);

        self::assertResponseStatusCodeSame(201);
        self::assertNotLogged('a Mercure publish failure', self::isMercurePublishFailure(...));
    }

    public function test_a_transport_failure_from_the_http_client_while_publishing_returns_201_and_logs_the_failure(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        HubStub::failWith(new TransportException('Connection refused'));

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => 'primary', 'uiComponent' => 'PrimaryLayout']);

        self::assertResponseStatusCodeSame(201);
        $layout = self::jsonNode('@id');
        self::assertResourceExists($layout);
        self::assertLogged(
            'the HTTP client publish failure for ' . $layout,
            static fn (LogRecord $record): bool => ($record->context['exception'] ?? null) instanceof HttpClientExceptionInterface
                && Level::Error === $record->level
                && \is_string($record->context['resource'] ?? null)
                && str_ends_with($record->context['resource'], $layout),
        );
    }

    public function test_an_invalid_mercure_jwt_is_configuration_so_the_publish_still_throws_after_the_write_is_saved(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        HubStub::failWith(new MercureInvalidArgumentException('The provided JWT is not valid.'));

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => 'primary', 'uiComponent' => 'PrimaryLayout']);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(1, self::countEntities(Layout::class));
        self::assertNotLogged('a swallowed publish failure', static fn (LogRecord $record): bool => ($record->context['exception'] ?? null) instanceof MercureInvalidArgumentException && \array_key_exists('topics', $record->context));
    }

    private static function isMercurePublishFailure(LogRecord $record): bool
    {
        return ($record->context['exception'] ?? null) instanceof MercureRuntimeException;
    }
}
