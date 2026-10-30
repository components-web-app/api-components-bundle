<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Identifier;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class AcceptAnyIdentifierTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ResourceTrait;
    use SiteTrait;

    public function test_a_configured_class_reaches_its_provider_with_an_identifier_that_is_not_a_uuid(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::createPage('share-resource-page');

        self::requestJsonLd($client, 'GET', '/_/pages/share-resource-page');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($page));
        self::assertJsonNodeSame('reference', 'share-resource-page');
    }
}
