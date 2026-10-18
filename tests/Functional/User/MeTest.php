<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\User;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class MeTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_i_can_retrieve_the_current_logged_in_user_object_by_its_iri(): void
    {
        $client = static::createClient();
        $userIri = self::iriOf(self::loginAsUser($client));

        self::requestJsonLd($client, 'GET', $userIri);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertJsonNodeSame('@id', $userIri);
        self::assertJsonNodeSame('_metadata.mercureSubscribeTopics[0]', 'http://example.com/_/component_groups/:id\?');
        self::assertResponseHeaderSame('cache-control', 'max-age=0, private');
    }

    public function test_i_can_retrieve_the_current_logged_in_user_object_from_me(): void
    {
        $client = static::createClient();
        $userIri = self::iriOf(self::loginAsUser($client));

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertJsonNodeSame('@id', $userIri);
        self::assertJsonNodeSame('@type', 'User');
        self::assertJsonNodeSame('@context', '/contexts/User');
        self::assertJsonNodeSame('_metadata.mercureSubscribeTopics[0]', 'http://example.com/_/component_groups/:id\?');
        self::assertResponseHeaderSame('cache-control', 'max-age=0, private');
    }

    public function test_an_anonymous_request_to_me_is_a_401(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
    }
}
