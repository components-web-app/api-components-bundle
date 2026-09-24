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
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Helper\RelativeUrlPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[AllowMockObjectsWithoutExpectations]
class PasswordResetEmailFactoryTest extends AbstractFinalEmailFactoryTestCase
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

    public function test_exception_thrown_if_no_website_name(): void
    {
        $user = new class extends AbstractUser {
        };
        $user->setUsername('username')->setEmailAddress('email@address.com');
        $user->plainNewPasswordConfirmationToken = 'token';

        $factory = new PasswordResetEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default-path');

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $refererUrlMock->expects(self::once())->method('getAbsoluteUrl')->with(RelativeUrlPath::fromConfiguration('/default-path'))->willReturn('/transformed-path');

        $invokedCount = self::exactly(1);
        $expectations = [
            [[RefererUrlResolver::class], $refererUrlMock],
        ];
        $this->containerInterfaceMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$params) use ($invokedCount, $expectations) {
                $i = $invokedCount->numberOfInvocations() - 1;
                $this->assertSame($expectations[$i][0], $params);

                return $expectations[$i][1];
            });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('website_name');

        $factory->create($user, []);
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
