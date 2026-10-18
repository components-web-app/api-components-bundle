<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Orphan;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Depends;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;

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

    private static function isMercurePublishFailure(LogRecord $record): bool
    {
        return ($record->context['exception'] ?? null) instanceof MercureRuntimeException;
    }
}
