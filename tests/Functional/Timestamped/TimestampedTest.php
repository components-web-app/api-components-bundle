<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Timestamped;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestampedWithSerializationGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUnguardedTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;

final class TimestampedTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use MercureTrait;
    use ResourceTrait;

    private const string CREATED_AT = '2001-02-03T04:05:06+00:00';

    private const string OVERWRITE = '{"createdAt": "1970-01-01T00:00:00+00:00", "modifiedAt": "1970-01-01T00:00:00+00:00"}';

    public function test_a_new_timestamped_resource_can_be_created(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/dummy_timestampeds', '{}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('timestamped.schema.json');
    }

    public function test_the_timestamped_fields_cannot_be_written(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'POST', '/dummy_timestampeds', '{"createdAt": "1970-01-01 00:00:00", "modifiedAt": "1970-01-01 00:00:00"}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('timestamped.schema.json');
        self::assertJsonNodeIsNow('createdAt', $now);
        self::assertJsonNodeIsNow('modifiedAt', $now);
        $updates = self::mercureUpdatesInLastRequest();
        self::assertCount(1, $updates);
        self::assertArrayHasKey('createdAt', self::mercureUpdateData($updates[0]));
        self::assertArrayHasKey('modifiedAt', self::mercureUpdateData($updates[0]));
    }

    public function test_created_at_cannot_be_overwritten_with_a_patch_request(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $iri = self::persistCreatedAt(new DummyTimestamped());
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'PATCH', $iri, self::OVERWRITE);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('createdAt', self::CREATED_AT);
        self::assertJsonNodeIsNow('modifiedAt', $now);
    }

    public function test_created_at_cannot_be_overwritten_with_a_patch_request_on_a_resource_without_a_guarded_setter(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $iri = self::persistCreatedAt(new DummyUnguardedTimestamped());
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'PATCH', $iri, self::OVERWRITE);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('createdAt', self::CREATED_AT);
        self::assertJsonNodeIsNow('modifiedAt', $now);
    }

    public function test_custom_timestamped_fields_can_be_used(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $now = new \DateTimeImmutable();
        $resource = new DummyCustomTimestamped();
        self::persistAndFlush($resource);

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeIsNow('customCreatedAt', $now);
        self::assertJsonNodeIsNow('customModifiedAt', $now);
    }

    public function test_timestamped_fields_are_serialized_on_a_resource_with_serialization_groups(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $now = new \DateTimeImmutable();
        $resource = new DummyTimestampedWithSerializationGroups();
        self::persistAndFlush($resource);

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeIsNow('createdAt', $now);
        self::assertJsonNodeIsNow('modifiedAt', $now);
    }

    private static function persistCreatedAt(DummyTimestamped|DummyUnguardedTimestamped $resource): string
    {
        $resource->createdAt = new \DateTimeImmutable(self::CREATED_AT);
        $resource->modifiedAt = new \DateTime(self::CREATED_AT);
        $manager = static::getEntityManager();
        $manager->persist($resource);
        $manager->flush();
        static::rebootKernel();

        return self::iriOf($resource);
    }
}
