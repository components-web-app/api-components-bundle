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

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Helper\User\CurrentUserMatcher;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UserNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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

    public function test_the_reachable_roles_are_written_into_the_output_and_never_onto_the_user(): void
    {
        $user = new User('admin', roles: ['ROLE_ADMIN']);

        $output = $this->createNormalizer($user, ['ROLE_ADMIN' => ['ROLE_CMS_ADMIN'], 'ROLE_CMS_ADMIN' => ['ROLE_USER']])->normalize($user);

        self::assertSame(['username' => 'admin', 'roles' => ['ROLE_ADMIN', 'ROLE_CMS_ADMIN', 'ROLE_USER']], $output);
        self::assertSame(['ROLE_ADMIN'], $user->getRoles());
    }

    public function test_an_output_without_roles_gets_none(): void
    {
        $user = new User('admin', roles: ['ROLE_ADMIN']);

        self::assertSame(['username' => 'admin'], $this->createNormalizer($user, ['ROLE_ADMIN' => ['ROLE_USER']], false)->normalize($user));
    }

    public function test_the_current_user_is_normalized_with_the_self_group_and_always_carries_admin_preferences(): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer(['username' => 'new_user']);

        $output = $this->createSelfNormalizer($user, $inner)->normalize(self::copyOf($user), null, ['groups' => ['User:output'], 'operation' => new Get()]);

        self::assertSame(['User:output', 'User:self'], $inner->context['groups']);
        self::assertSame(['username' => 'new_user', 'adminPreferences' => ['topBar' => ['pages']]], $output);
    }

    public function test_the_current_user_without_admin_preferences_carries_null(): void
    {
        $user = self::savedUser();
        $user->setAdminPreferences(null);

        $output = $this->createSelfNormalizer($user, new RecordingUserSerializer(['username' => 'new_user']))->normalize($user, null, ['groups' => ['User:output']]);

        self::assertSame(['username' => 'new_user', 'adminPreferences' => null], $output);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function contextsThatAreNeverSelf(): iterable
    {
        yield 'a collection operation' => [['groups' => ['User:output'], 'operation' => new GetCollection()]];
        yield 'an item of a collection' => [['groups' => ['User:output'], 'root_operation' => new GetCollection(), 'operation' => new Get()]];
        yield 'a mercure update' => [['groups' => ['User:output'], MercureResourcePublisher::NORMALIZATION_CONTEXT_KEY => true]];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('contextsThatAreNeverSelf')]
    public function test_the_current_user_is_not_self_in_a_collection_or_a_mercure_update(array $context): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer(['username' => 'new_user', 'adminPreferences' => ['topBar' => ['pages']]]);

        $output = $this->createSelfNormalizer($user, $inner)->normalize($user, null, $context);

        self::assertSame(['User:output'], $inner->context['groups']);
        self::assertSame(['username' => 'new_user'], $output);
    }

    public function test_another_user_never_carries_admin_preferences_even_without_groups(): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer(['username' => 'other_user', 'adminPreferences' => ['topBar' => ['pages']]]);

        $output = $this->createSelfNormalizer(self::savedUser(), $inner)->normalize($user);

        self::assertArrayNotHasKey('groups', $inner->context);
        self::assertSame(['username' => 'other_user'], $output);
    }

    public function test_the_current_user_without_groups_gets_no_groups_added(): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer(['username' => 'new_user']);

        $this->createSelfNormalizer($user, $inner)->normalize($user);

        self::assertArrayNotHasKey('groups', $inner->context);
    }

    public function test_the_current_user_with_groups_that_are_not_a_list_gets_no_group_added(): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer(['username' => 'new_user']);

        $this->createSelfNormalizer($user, $inner)->normalize($user, null, ['groups' => 'User:output']);

        self::assertSame('User:output', $inner->context['groups']);
    }

    public function test_the_stored_user_is_compared_as_it_was_before_the_payload_was_applied(): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer([], $user, static fn (User $populated) => $populated->setNewEmailAddress('new@example.com'));

        $this->createSelfNormalizer($user, $inner)->denormalize(['newEmailAddress' => 'new@example.com'], User::class, null, ['groups' => ['User:input'], AbstractNormalizer::OBJECT_TO_POPULATE => $user]);

        self::assertSame('hashed', $user->getNewEmailConfirmationToken());
    }

    public function test_a_normalized_value_that_is_not_an_array_is_returned_as_it_is(): void
    {
        $user = self::savedUser();

        self::assertSame('/users/1', $this->createSelfNormalizer($user, new RecordingUserSerializer('/users/1'))->normalize($user));
    }

    public function test_the_current_user_denormalizes_admin_preferences_with_the_self_group(): void
    {
        $user = self::savedUser();
        $inner = new RecordingUserSerializer([], $user);

        $this->createSelfNormalizer($user, $inner)->denormalize(['adminPreferences' => ['topBar' => []]], User::class, null, ['groups' => ['User:input'], AbstractNormalizer::OBJECT_TO_POPULATE => self::copyOf($user)]);

        self::assertSame(['User:input', 'User:self'], $inner->context['groups']);
        self::assertSame(['adminPreferences' => ['topBar' => []]], $inner->data);
    }

    public function test_another_user_never_denormalizes_admin_preferences(): void
    {
        $other = self::savedUser();
        $inner = new RecordingUserSerializer([], $other);

        $this->createSelfNormalizer(self::savedUser(), $inner)->denormalize(['adminPreferences' => ['topBar' => []], 'enabled' => false], User::class, null, ['groups' => ['User:input'], AbstractNormalizer::OBJECT_TO_POPULATE => $other]);

        self::assertSame(['User:input'], $inner->context['groups']);
        self::assertSame(['enabled' => false], $inner->data);
    }

    public function test_a_new_user_never_denormalizes_admin_preferences(): void
    {
        $inner = new RecordingUserSerializer([], new User());

        $this->createSelfNormalizer(self::savedUser(), $inner)->denormalize(['username' => 'new_user', 'adminPreferences' => ['topBar' => []]], User::class, null, ['groups' => ['User:input']]);

        self::assertSame(['User:input'], $inner->context['groups']);
        self::assertSame(['username' => 'new_user'], $inner->data);
    }

    private static function savedUser(): User
    {
        $user = new User('new_user', 'user@example.com');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, Uuid::uuid4());
        $user->setAdminPreferences(['topBar' => ['pages']]);

        return $user;
    }

    private static function copyOf(User $user): User
    {
        return clone $user;
    }

    private function createSelfNormalizer(User $current, RecordingUserSerializer $inner): UserNormalizer
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($current, 'main', $current->getRoles()));
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
            new CurrentUserMatcher($tokenStorage),
        );
        $normalizer->setNormalizer($inner);
        $normalizer->setDenormalizer($inner);

        return $normalizer;
    }

    /**
     * @param array<string, list<string>> $hierarchy
     */
    private function createNormalizer(User $denormalized, array $hierarchy = [], bool $outputsRoles = true): UserNormalizer
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
            new RoleHierarchy($hierarchy),
            new ResourceMetadataProvider(),
            $this->createStub(MercureAuthorization::class),
            new CurrentUserMatcher(new TokenStorage()),
        );

        $inner = $this->createStub(DenormalizerInterface::class);
        $inner->method('denormalize')->willReturn($denormalized);
        $normalizer->setDenormalizer($inner);
        $innerNormalizer = $this->createStub(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturnCallback(static fn (User $user): array => $outputsRoles ? ['username' => $user->getUsername(), 'roles' => $user->getRoles()] : ['username' => $user->getUsername()]);
        $normalizer->setNormalizer($innerNormalizer);

        return $normalizer;
    }
}

final class RecordingUserSerializer implements NormalizerInterface, DenormalizerInterface
{
    /** @var array<string, mixed> */
    public array $context = [];

    public mixed $data = null;

    public function __construct(private readonly array|string $normalized, private readonly ?User $denormalized = null, private readonly ?\Closure $onDenormalize = null)
    {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        $this->context = $context;

        return $this->normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $this->context = $context;
        $this->data = $data;
        if (null !== $this->onDenormalize && null !== $this->denormalized) {
            ($this->onDenormalize)($this->denormalized);
        }

        return $this->denormalized;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }
}
