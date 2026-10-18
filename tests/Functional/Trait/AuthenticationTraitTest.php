<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class AuthenticationTraitTest extends FunctionalTestCase
{
    use AuthenticationTrait;

    public function test_a_bearer_token_authenticates_the_user_it_was_made_for(): void
    {
        $client = static::createClient();
        $user = self::createUser(['ROLE_ADMIN'], 'token_user', 'token@example.com');

        self::addBearerToken($client, $user);
        $response = $client->request('GET', '/me', ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('token_user', $response->toArray()['username']);
    }
}
