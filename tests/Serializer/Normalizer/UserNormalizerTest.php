<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UserNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[CoversClass(UserNormalizer::class)]
final class UserNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string, array<string, mixed>, bool}>
     */
    public static function denormalizationCases(): iterable
    {
        yield 'a user payload' => [['username' => 'new_user'], User::class, [], true];
        yield 'an IRI referencing a user' => ['/users/0198f2a4-6f1e-7c3b-9a2d-5e4f3a2b1c0d', User::class, [], false];
        yield 'a user payload already handled' => [['username' => 'new_user'], User::class, [UserNormalizer::ALREADY_CALLED => true], false];
        yield 'a payload for another class' => [['username' => 'new_user'], DummyComponent::class, [], false];
    }

    #[DataProvider('denormalizationCases')]
    public function test_it_denormalizes_only_a_user_payload(mixed $data, string $type, array $context, bool $supported): void
    {
        self::assertSame($supported, $this->createNormalizer(new User())->supportsDenormalization($data, $type, null, $context));
    }

    public function test_a_new_user_payload_is_processed_as_a_registration(): void
    {
        $user = new User(emailAddressVerified: true);

        $result = $this->createNormalizer($user)->denormalize(['username' => 'new_user'], User::class);

        self::assertSame($user, $result);
        self::assertFalse($user->isEmailAddressVerified());
        self::assertSame('hashed', $user->getEmailAddressVerifyToken());
    }

    private function createNormalizer(User $denormalized): UserNormalizer
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $normalizer = new UserNormalizer(
            new UserDataProcessor(
                $hasher,
                $this->createStub(UserRepositoryInterface::class),
                $this->createStub(PasswordHasherFactoryInterface::class),
                false,
                true,
                false,
            ),
            new RoleHierarchy([]),
            new ResourceMetadataProvider(),
            $this->createStub(MercureAuthorization::class),
        );

        $inner = $this->createStub(DenormalizerInterface::class);
        $inner->method('denormalize')->willReturn($denormalized);
        $normalizer->setDenormalizer($inner);

        return $normalizer;
    }
}
