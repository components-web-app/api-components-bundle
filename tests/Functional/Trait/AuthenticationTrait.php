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

use ApiPlatform\Test\Client;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait AuthenticationTrait
{
    protected static function loginAsAdmin(Client $client): User
    {
        $user = static::createUser(['ROLE_ADMIN']);
        $client->getKernelBrowser()->getCookieJar()->set(new Cookie('api_components', static::createToken($user)));

        return $user;
    }

    protected static function loginAsSuperAdmin(Client $client): User
    {
        $user = static::createUser(['ROLE_SUPER_ADMIN']);
        $client->getKernelBrowser()->getCookieJar()->set(new Cookie('api_components', static::createToken($user)));

        return $user;
    }

    protected static function loginAsUser(Client $client): User
    {
        $user = static::createUser(['ROLE_USER']);
        $client->getKernelBrowser()->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . static::createToken($user));

        return $user;
    }

    /**
     * @param list<string> $roles
     */
    protected static function createUser(array $roles, string $username = 'new_user', string $emailAddress = 'user@example.com', string $password = 'password'): User
    {
        $container = static::getContainer();
        $user = new User();
        $user
            ->setRoles($roles)
            ->setUsername($username)
            ->setEmailAddress($emailAddress)
            ->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $password))
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $container->get(TimestampedDataPersister::class)->persistTimestampedFields($user, true);
        $manager = static::getEntityManager();
        $manager->persist($user);
        $manager->flush();

        return $user;
    }

    protected static function createToken(User $user): string
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        static::rebootKernel();

        return $token;
    }
}
