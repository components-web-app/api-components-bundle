<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Security\AdminRoles;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Validator\Constraints\AssignableRoles;
use Silverback\ApiComponentsBundle\Validator\Constraints\AssignableRolesValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddress;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class AssignableRolesValidatorTest extends ConstraintValidatorTestCase
{
    private ?ObjectManager $manager = null;
    private TokenStorage $tokenStorage;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->tokenStorage = new TokenStorage();
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (string $class): ?ObjectManager => User::class === $class ? $this->manager : null);

        return new AssignableRolesValidator($registry, $this->tokenStorage, new RoleHierarchy(AdminRoles::HIERARCHY));
    }

    public function test_a_role_the_caller_does_not_reach_is_refused_on_roles(): void
    {
        $this->signIn(['ROLE_USER_ADMIN']);
        $user = new User('subject', roles: ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SHOP']);
        $this->storeOriginalRoles($user, ['ROLE_USER']);

        $this->validator->validate($user, new AssignableRoles());

        $this->buildViolation('You may not assign the role(s) {{ roles }}.')
            ->setParameter('{{ roles }}', 'ROLE_ADMIN, ROLE_SHOP')
            ->atPath('property.path.roles')
            ->assertRaised();
    }

    public function test_a_role_the_caller_reaches_is_accepted(): void
    {
        $this->signIn(['ROLE_USER_ADMIN']);
        $user = new User('subject', roles: ['ROLE_USER', 'ROLE_USER_ADMIN']);
        $this->storeOriginalRoles($user, ['ROLE_USER']);

        $this->validator->validate($user, new AssignableRoles());

        $this->assertNoViolation();
    }

    public function test_a_role_the_user_already_has_is_never_refused(): void
    {
        $this->signIn(['ROLE_USER_ADMIN']);
        $user = new User('subject', roles: ['ROLE_SHOP']);
        $this->storeOriginalRoles($user, ['ROLE_SHOP', 'ROLE_USER']);

        $this->validator->validate($user, new AssignableRoles());

        $this->assertNoViolation();
    }

    public function test_every_role_of_a_new_user_is_checked(): void
    {
        $this->signIn(['ROLE_USER_ADMIN']);
        $user = new User('subject', roles: ['ROLE_SUPER_ADMIN']);
        $this->storeOriginalRoles($user, null);

        $this->validator->validate($user, new AssignableRoles());

        $this->buildViolation('You may not assign the role(s) {{ roles }}.')
            ->setParameter('{{ roles }}', 'ROLE_SUPER_ADMIN')
            ->atPath('property.path.roles')
            ->assertRaised();
    }

    public function test_a_user_with_no_orm_manager_has_every_role_checked(): void
    {
        $this->signIn(['ROLE_CMS_ADMIN']);
        $this->manager = $this->createStub(ObjectManager::class);

        $this->validator->validate(new User('subject', roles: ['ROLE_USER', 'ROLE_USER_ADMIN']), new AssignableRoles());

        $this->buildViolation('You may not assign the role(s) {{ roles }}.')
            ->setParameter('{{ roles }}', 'ROLE_USER_ADMIN')
            ->atPath('property.path.roles')
            ->assertRaised();
    }

    public function test_a_super_admin_assigns_any_role_they_reach(): void
    {
        $this->signIn(['ROLE_SUPER_ADMIN']);
        $user = new User('subject', roles: ['ROLE_SUPER_ADMIN', 'ROLE_ALLOWED_TO_SWITCH', 'ROLE_CMS_ADMIN']);
        $this->storeOriginalRoles($user, ['ROLE_USER']);

        $this->validator->validate($user, new AssignableRoles());

        $this->assertNoViolation();
    }

    public function test_with_no_token_nothing_is_checked(): void
    {
        $this->manager = $this->createStub(ObjectManager::class);

        $this->validator->validate(new User('subject', roles: ['ROLE_SUPER_ADMIN']), new AssignableRoles());

        $this->assertNoViolation();
    }

    public function test_with_an_anonymous_token_nothing_is_checked(): void
    {
        $this->tokenStorage->setToken(new NullToken());
        $this->manager = $this->createStub(ObjectManager::class);

        $this->validator->validate(new User('subject', roles: ['ROLE_SUPER_ADMIN']), new AssignableRoles());

        $this->assertNoViolation();
    }

    public function test_the_message_can_be_changed(): void
    {
        $this->signIn(['ROLE_USER']);
        $this->manager = $this->createStub(ObjectManager::class);
        $constraint = new AssignableRoles();
        $constraint->message = 'No {{ roles }}.';

        $this->validator->validate(new User('subject', roles: ['ROLE_ADMIN']), $constraint);

        $this->buildViolation('No {{ roles }}.')
            ->setParameter('{{ roles }}', 'ROLE_ADMIN')
            ->atPath('property.path.roles')
            ->assertRaised();
    }

    public function test_it_is_a_class_constraint(): void
    {
        self::assertSame(AssignableRoles::CLASS_CONSTRAINT, (new AssignableRoles())->getTargets());
    }

    public function test_another_constraint_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new User('subject'), new NewEmailAddress());
    }

    public function test_a_value_that_is_not_a_user_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new AssignableRoles());
    }

    /**
     * @param list<string> $roles
     */
    private function signIn(array $roles): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new User('caller', roles: $roles), 'main', $roles));
    }

    /**
     * @param list<string>|null $roles
     */
    private function storeOriginalRoles(User $user, ?array $roles): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(static fn (object $entity): array => $entity === $user && null !== $roles ? ['roles' => $roles] : []);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $this->manager = $manager;
    }
}
