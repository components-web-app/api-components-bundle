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

final class SuperAdminPropertiesTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_a_super_admin_reads_the_properties_not_available_to_a_normal_user(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('my_user', self::generatedSecret(), 'ROLE_ADMIN');

        self::requestJsonLd($client, 'GET', self::iriOf($user));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('user_super_admin.schema.json');
    }
}
