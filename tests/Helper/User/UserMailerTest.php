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

namespace Silverback\ApiComponentsBundle\Tests\Helper\User;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\AbstractUserEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class UserMailerTest extends TestCase
{
    private const TEST_CONTEXT = ['context_key' => 'context_value'];

    /**
     * @var MockObject|MailerInterface
     */
    private MockObject $mailerMock;
    /**
     * @var MockObject|ContainerInterface
     */
    private MockObject $containerMock;
    private UserMailer $userMailer;

    protected function setUp(): void
    {
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->userMailer = new UserMailer($this->mailerMock, $this->containerMock, self::TEST_CONTEXT);
    }

    public function test_context_can_be_omitted(): void
    {
        $user = new class extends AbstractUser {
        };

        $userMailer = new UserMailer($this->mailerMock, $this->containerMock);

        $factoryMock = $this->getFactoryFromContainerMock(PasswordResetEmailFactory::class);

        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, [])
            ->willReturn(null);

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_send_method_skipped_if_no_message_returned(): void
    {
        $user = new class extends AbstractUser {
        };

        $factoryMock = $this->getFactoryFromContainerMock(PasswordResetEmailFactory::class);

        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn(null);

        $this->mailerMock
            ->expects($this->never())
            ->method('send');

        $this->userMailer->sendPasswordResetEmail($user);
    }

    public function test_exception_thrown_if_mailer_send_throws_exception(): void
    {
        $user = new class extends AbstractUser {
        };
        $templateEmail = new TemplatedEmail();

        $additionalExpectations = $this->createEmMockExpectation();

        $loggerMock = $this->createMock(Logger::class);
        $factoryMock = $this->getFactoryFromContainerMock(PasswordResetEmailFactory::class, [...$additionalExpectations, ['logger', $loggerMock]]);

        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn($templateEmail);

        $mockException = $this->createMock(TransportExceptionInterface::class);
        $this->mailerMock
            ->expects(self::once())
            ->method('send')
            ->with($templateEmail)
            ->willThrowException($mockException);

        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with($mockException->getMessage());

        $this->userMailer->sendPasswordResetEmail($user);
    }

    public function test_send_password_reset_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_password_reset_email';
        };

        $additionalExpectations = $this->createEmMockExpectation();

        $this->expectFactoryCallAndSendMailerMethod(PasswordResetEmailFactory::class, $user, $additionalExpectations);

        $this->userMailer->sendPasswordResetEmail($user);
    }

    public function test_send_change_email_verification_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_change_email_verification_email';
        };

        $additionalExpectations = $this->createEmMockExpectation();

        $this->expectFactoryCallAndSendMailerMethod(ChangeEmailConfirmationEmailFactory::class, $user, $additionalExpectations);

        $this->userMailer->sendChangeEmailConfirmationEmail($user);
    }

    public function test_send_welcome_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_welcome_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(WelcomeEmailFactory::class, $user);

        $this->userMailer->sendWelcomeEmail($user);
    }

    public function test_send_user_enabled_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_user_enabled_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(UserEnabledEmailFactory::class, $user);

        $this->userMailer->sendUserEnabledEmail($user);
    }

    public function test_send_username_changed_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_username_changed_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(UsernameChangedEmailFactory::class, $user);

        $this->userMailer->sendUsernameChangedEmail($user);
    }

    public function test_send_password_changed_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_password_changed_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(PasswordChangedEmailFactory::class, $user);

        $this->userMailer->sendPasswordChangedEmail($user);
    }

    private function expectFactoryCallAndSendMailerMethod(string $factoryClass, AbstractUser $user, array $additionalExpectations = []): void
    {
        $templateEmail = new TemplatedEmail();

        $factoryMock = $this->getFactoryFromContainerMock($factoryClass, $additionalExpectations);

        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn($templateEmail);

        $this->expectMailerSendMethod($templateEmail);
    }

    private function createEmMockExpectation(): array
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects(self::once())->method('flush');

        return [
            [
                EntityManagerInterface::class,
                $emMock,
            ],
        ];
    }

    private function getFactoryFromContainerMock(string $factory, array $additionalExpectations = []): MockObject
    {
        $factoryMock = $this->createMock(AbstractUserEmailFactory::class);
        $expectations = [
            [$factory, $factoryMock],
            ...$additionalExpectations,
        ];

        $invokedCount = self::exactly(\count($expectations));

        $this->containerMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function ($parameters) use ($invokedCount, $expectations) {
                $currentInvocationCount = $invokedCount->numberOfInvocations();
                $currentExpectation = $expectations[$currentInvocationCount - 1];
                $this->assertSame($currentExpectation[0], $parameters);

                return $currentExpectation[1];
            });

        return $factoryMock;
    }

    private function expectMailerSendMethod(?RawMessage $message): void
    {
        $this->mailerMock
            ->expects(self::once())
            ->method('send')
            ->with($message);
    }
}
