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

final class UserReferenceTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_referencing_a_verified_user_by_iri_from_another_resource_leaves_the_user_unchanged(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $user = self::createUserWithRole('referenced_user', self::generatedSecret(), 'ROLE_USER', 'referenced@example.com');

        self::requestJsonLd($client, 'POST', '/dummy_user_references', ['user' => self::iriOf($user)]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('user', self::iriOf($user));
        $stored = self::findUserByUsername('referenced_user');
        self::assertNotNull($stored);
        self::assertTrue($stored->isEmailAddressVerified(), 'The referenced user is no longer verified.');
        self::assertNull($stored->getEmailAddressVerifyToken(), 'The referenced user was given an email verification token.');
        self::assertNoEmailSent();
    }
}
