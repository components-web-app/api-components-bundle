<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Realtime;

use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\Realtime\RealtimeUserResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class RealtimeUserResolverTest extends TestCase
{
    public function test_a_bundle_user_is_identified_by_its_iri_and_named_by_its_username_never_its_email(): void
    {
        $user = (new User())->setUsername('editor')->setEmailAddress('editor@example.com');
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->expects(self::once())->method('getIriFromResource')->with($user)->willReturn('/users/1');

        self::assertSame(['id' => '/users/1', 'name' => 'editor'], $this->resolver($user, $iriConverter)->resolve());
    }

    public function test_any_other_user_is_identified_and_named_by_its_user_identifier(): void
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->expects(self::never())->method('getIriFromResource');

        self::assertSame(['id' => 'admin', 'name' => 'admin'], $this->resolver(new InMemoryUser('admin', null, ['ROLE_ADMIN']), $iriConverter)->resolve());
    }

    public function test_there_is_no_user_without_a_token(): void
    {
        self::assertNull((new RealtimeUserResolver(new TokenStorage(), $this->createStub(IriConverterInterface::class)))->resolve());
    }

    public function test_there_is_no_user_for_an_anonymous_token(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new NullToken());

        self::assertNull((new RealtimeUserResolver($tokenStorage, $this->createStub(IriConverterInterface::class)))->resolve());
    }

    private function resolver(UserInterface $user, IriConverterInterface $iriConverter): RealtimeUserResolver
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return new RealtimeUserResolver($tokenStorage, $iriConverter);
    }
}
