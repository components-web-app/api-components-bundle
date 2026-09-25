<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Form\Type\User;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Command\InMemoryUserRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StoredUserFormTypeTest extends TestCase
{
    public static function formTypes(): iterable
    {
        yield 'new email address' => [NewEmailAddressType::class];
        yield 'change password' => [ChangePasswordType::class];
    }

    /**
     * @param class-string<AbstractType> $formType
     */
    #[DataProvider('formTypes')]
    public function test_the_form_is_built_on_the_stored_user_with_the_logged_in_username(string $formType): void
    {
        $storedUser = new User('bob', 'bob@example.com');
        $other = new User('alice', 'alice@example.com');

        self::assertSame($storedUser, $this->resolveEmptyData($formType, new User('BOB', 'stale@example.com'), new InMemoryUserRepository([$other, $storedUser])));
    }

    /**
     * @param class-string<AbstractType> $formType
     */
    #[DataProvider('formTypes')]
    public function test_a_user_whose_email_address_is_the_logged_in_username_is_not_the_stored_user(string $formType): void
    {
        $other = new User('alice', 'bob');

        self::assertNull($this->resolveEmptyData($formType, new User('bob', 'bob@example.com'), new InMemoryUserRepository([$other])));
    }

    /**
     * @param class-string<AbstractType> $formType
     */
    #[DataProvider('formTypes')]
    public function test_a_username_matching_more_than_one_user_has_no_stored_user(string $formType): void
    {
        $storedUser = new User('bob', 'bob@example.com');
        $other = new User('alice', 'bob');

        self::assertNull($this->resolveEmptyData($formType, new User('bob', 'bob@example.com'), new InMemoryUserRepository([$storedUser, $other])));
    }

    /**
     * @param class-string<AbstractType> $formType
     */
    #[DataProvider('formTypes')]
    public function test_a_user_class_that_is_not_a_bundle_user_is_refused_by_name(string $formType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The user class `%s` provided to the form `%s` must extend', \stdClass::class, $formType));

        new $formType($this->createStub(Security::class), new InMemoryUserRepository([]), \stdClass::class);
    }

    /**
     * @param class-string<AbstractType> $formType
     */
    private function resolveEmptyData(string $formType, AbstractUser $loggedInUser, UserRepositoryInterface $repository): mixed
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedInUser);
        $type = new $formType($security, $repository, User::class);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        return $resolver->resolve()['empty_data'];
    }
}
