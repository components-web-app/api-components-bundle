<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;

final class ExceptionToStatusTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ResourceTrait;

    public function test_a_relation_given_an_invalid_iri_is_a_400_from_api_platforms_serializer_mapping(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::persistAndFlush(new DummyComponent());

        self::requestJsonLd($client, 'POST', '/_/component_positions', '{"componentGroup": "/_/not_a_resource/123", "component": "/component/dummy_components/abc"}');

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('Invalid IRI', (string) self::jsonNode('detail'));
    }

    public function test_an_applications_more_specific_mapping_still_wins_over_api_platforms_default(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/_/component_groups', ['reference' => 'header', 'location' => 'header', 'maxComponents' => 'not-a-number']);

        self::assertResponseStatusCodeSame(422);
    }
}
