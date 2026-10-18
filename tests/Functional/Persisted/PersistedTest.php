<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Persisted;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;

final class PersistedTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use JsonTrait;
    use ResourceTrait;

    public function test_an_object_persisted_to_the_database_is_marked_persisted(): void
    {
        $client = static::createClient();
        $component = new DummyComponent();
        self::persistAndFlush($component);

        self::requestJsonLd($client, 'GET', self::iriOf($component));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('persisted.schema.json');
        self::assertJsonNodeSame('_metadata.persisted', true);
    }

    public function test_an_object_not_persisted_to_the_database_is_marked_not_persisted(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/dummy_unpersisted_components/123');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('persisted.schema.json');
        self::assertJsonNodeSame('_metadata.persisted', false);
    }
}
