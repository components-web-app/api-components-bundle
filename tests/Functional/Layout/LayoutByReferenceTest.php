<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Layout;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class LayoutByReferenceTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use ResourceTrait;
    use SiteTrait;

    public function test_a_layout_is_found_by_its_reference_with_its_uuid_iri(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::createLayout('primary');

        self::requestJsonLd($client, 'GET', '/_/layouts/primary');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($layout));
    }

    public function test_a_reference_wins_over_another_layouts_uuid(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $id = (string) self::createLayout('by-id')->getId();
        $byReference = self::createLayout($id);

        self::requestJsonLd($client, 'GET', '/_/layouts/' . $id);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($byReference));
    }

    public function test_an_unknown_reference_is_not_found(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'GET', '/_/layouts/unknown');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_layout_read_by_reference_is_tagged_with_its_iri_which_a_patch_purges(): void
    {
        $client = static::createClient();
        $iri = self::iriOf(self::createLayout('primary'));

        self::requestJsonLd($client, 'GET', '/_/layouts/primary');
        self::assertResponseHasCacheTag($iri);

        self::loginAsAdmin($client);
        self::requestJsonLd($client, 'PATCH', $iri, ['uiComponent' => 'CwaLayoutSecondary']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($iri);
    }

    public function test_a_layout_is_patched_by_its_reference(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::createLayout('primary');

        self::requestJsonLd($client, 'PATCH', '/_/layouts/primary', ['uiComponent' => 'CwaLayoutSecondary']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($layout));
        self::assertJsonNodeSame('uiComponent', 'CwaLayoutSecondary');
    }

    public function test_a_layout_read_by_reference_is_public(): void
    {
        $client = static::createClient();
        $layout = self::createLayout('primary');

        self::requestJsonLd($client, 'GET', '/_/layouts/primary');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($layout));
    }
}
