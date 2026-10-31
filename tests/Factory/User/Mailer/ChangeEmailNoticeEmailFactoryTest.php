<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\User\Mailer;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailNoticeEmailFactory;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[AllowMockObjectsWithoutExpectations]
class ChangeEmailNoticeEmailFactoryTest extends AbstractFinalEmailFactoryTestCase
{
    public function test_skip_user_validation_if_disabled(): void
    {
        $factory = new ChangeEmailNoticeEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', false);
        $this->assertNull(
            $factory->create(
                new class extends AbstractUser {
                }
            )
        );
    }

    #[DataProvider('noNewEmailAddress')]
    public function test_exception_thrown_if_no_new_email_address(?string $newEmailAddress): void
    {
        $user = new class extends AbstractUser {
        };
        $user
            ->setUsername('username')
            ->setEmailAddress('email@address.com')
            ->setNewEmailAddress($newEmailAddress);

        $factory = new ChangeEmailNoticeEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A `new email address` must be set to send the change email notice');

        $factory->create($user);
    }

    public static function noNewEmailAddress(): iterable
    {
        yield 'null' => [null];
        yield 'a blank string' => [''];
    }

    public function test_the_notice_goes_to_the_current_address_with_the_new_one_masked_and_no_link(): void
    {
        $user = new class extends AbstractUser {
        };
        $user
            ->setUsername('username')
            ->setEmailAddress('email@address.com')
            ->setNewEmailAddress('new@address.com');

        $factory = new ChangeEmailNoticeEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject');

        $this->assertCommonMockMethodsCalled();

        $email = (new TemplatedEmail())
            ->to(Address::create('email@address.com'))
            ->subject('subject')
            ->htmlTemplate('@SilverbackApiComponents/emails/user_change_email_notice.html.twig')
            ->context(
                [
                    'website_name' => 'my website',
                    'user' => $user,
                    'masked_new_email_address' => 'n***@address.com',
                ]
            );

        $this->assertEmailEquals($email, $factory->create($user, ['website_name' => 'my website']), ChangeEmailNoticeEmailFactory::MESSAGE_ID_PREFIX);
    }
}
