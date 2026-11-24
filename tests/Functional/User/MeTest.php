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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

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

    public function test_me_lists_every_role_the_user_reaches(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);

        $roles = self::requestJsonLd($client, 'GET', '/me')->toArray()['roles'];
        sort($roles);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH', 'ROLE_CMS_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER', 'ROLE_USER_ADMIN'], $roles);
    }

    public function test_a_flush_after_a_user_is_normalized_stores_only_the_roles_it_was_given(): void
    {
        static::bootKernel();
        $user = self::createUser(['ROLE_ADMIN']);
        $manager = static::getEntityManager();
        $stored = $manager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $stored);

        $normalized = static::getContainer()->get('serializer')->normalize($stored, 'jsonld', ['groups' => ['User:output']]);
        $manager->flush();
        $manager->clear();

        self::assertIsArray($normalized);
        self::assertContains('ROLE_CMS_ADMIN', $normalized['roles']);
        self::assertSame(['ROLE_ADMIN'], $manager->find(User::class, $user->getId())?->getRoles());
    }

    public function test_an_anonymous_request_to_me_is_a_401(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
    }
}
