<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Factory\User\Mailer;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class PasswordResetEmailFactoryTest extends AbstractFinalEmailFactoryTest
{
    public function test_skip_user_validation_if_disabled(): void
    {
        $factory = new PasswordResetEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', false);
        $this->assertNull(
            $factory->create(
                new class extends AbstractUser {
                }
            )
        );
    }

    public function test_exception_thrown_if_no_token(): void
    {
        $user = new class extends AbstractUser {
        };
        $user
            ->setUsername('username')
            ->setEmailAddress('email@address.com');

        $factory = new PasswordResetEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A new password confirmation token must be set to send the `password reset` email');

        $factory->create($user);
    }

    public function test_redirect_url_context_added_and_html_template_passed(): void
    {
        $user = new class extends AbstractUser {
        };
        $user
            ->setUsername('username')
            ->setEmailAddress('email@address.com');
        $user->plainNewPasswordConfirmationToken = 'token';
        $factory = new PasswordResetEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default-path');

        $this->assertCommonMockMethodsCalled(true);

        $email = (new TemplatedEmail())
            ->to(Address::create('email@address.com'))
            ->subject('subject')
            ->htmlTemplate('@SilverbackApiComponents/emails/user_password_reset.html.twig')
            ->context(
                [
                    'website_name' => 'my website',
                    'user' => $user,
                    'redirect_url' => '/transformed-path',
                ]
            );

        $this->assertEmailEquals($email, $factory->create($user, ['website_name' => 'my website']), PasswordResetEmailFactory::MESSAGE_ID_PREFIX);
    }
}
