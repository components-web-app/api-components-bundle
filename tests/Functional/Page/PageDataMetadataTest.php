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
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class PageDataMetadataTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use JsonTrait;

    public function test_i_can_get_all_page_data_metadata(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/page_data_metadatas');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@context.properties', 'PageDataMetadata/properties');
        self::assertJsonNodeCount('member', 7);
    }

    public function test_i_can_get_a_single_page_data_endpoint(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/page_data_metadatas/Silverback%5CApiComponentsBundle%5CTests%5CFunctional%5CTestBundle%5CEntity%5CPageDataWithComponent');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('properties', 2);
        self::assertJsonNodeSame('properties[0].property', 'component');
        self::assertJsonNodeSame('properties[0].componentShortName', 'DummyComponent');
        self::assertJsonNodeNotExists('properties[0].componentClass');
    }
}
