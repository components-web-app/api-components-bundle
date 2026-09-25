<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\User;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\DisallowedRequestOriginException;
use Silverback\ApiComponentsBundle\Exception\MailerTransportException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\AbstractUserEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ServiceLocator;
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

        $this->mailerMock->expects($this->never())->method('send');

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_send_method_skipped_if_no_message_returned(): void
    {
        $user = new class extends AbstractUser {
        };
        $user->setPasswordRequestedAt(new \DateTime());

        $factoryMock = $this->getFactoryFromContainerMock(PasswordResetEmailFactory::class);

        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn(null);

        $this->mailerMock
            ->expects($this->never())
            ->method('send');

        $result = $this->userMailer->sendPasswordResetEmail($user);
        $this->assertFalse($result);
        $this->assertNull($user->getPasswordRequestedAt());
    }

    public function test_exception_thrown_if_mailer_send_throws_exception(): void
    {
        $user = new class extends AbstractUser {
        };
        $templateEmail = new TemplatedEmail();

        $additionalExpectations = $this->createEmMockExpectation();

        $loggerMock = $this->createMock(Logger::class);
        $factoryMock = $this->getFactoryFromContainerMock(PasswordResetEmailFactory::class, [...$additionalExpectations, [['logger'], $loggerMock]]);

        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn($templateEmail);

        $mockException = $this->createStub(TransportExceptionInterface::class);
        $this->mailerMock
            ->expects(self::once())
            ->method('send')
            ->with($templateEmail)
            ->willThrowException($mockException);

        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(static fn (array $ctx) => isset($ctx['exception']) && $ctx['exception'] instanceof MailerTransportException)
            );

        $result = $this->userMailer->sendPasswordResetEmail($user);
        $this->assertFalse($result);
    }

    public function test_send_password_reset_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_password_reset_email';
        };

        $additionalExpectations = $this->createEmMockExpectation();

        $this->expectFactoryCallAndSendMailerMethod(PasswordResetEmailFactory::class, $user, $additionalExpectations);

        $result = $this->userMailer->sendPasswordResetEmail($user);
        $this->assertTrue($result);
        $this->assertNotNull($user->getPasswordRequestedAt());
    }

    public function test_send_change_email_null_case(): void
    {
        $user = new class extends AbstractUser {
        };
        $user->setNewEmailAddressChangeRequestedAt(new \DateTime());

        $factoryMock = $this->getFactoryFromContainerMock(ChangeEmailConfirmationEmailFactory::class);
        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn(null);

        $this->mailerMock->expects($this->never())->method('send');

        $result = $this->userMailer->sendChangeEmailConfirmationEmail($user);
        $this->assertFalse($result);
        $this->assertNull($user->getNewEmailAddressChangeRequestedAt());
    }

    public function test_send_change_email_verification_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_change_email_verification_email';
        };

        $additionalExpectations = $this->createEmMockExpectation();

        $this->expectFactoryCallAndSendMailerMethod(ChangeEmailConfirmationEmailFactory::class, $user, $additionalExpectations);

        $result = $this->userMailer->sendChangeEmailConfirmationEmail($user);
        $this->assertTrue($result);
        $this->assertNotNull($user->getNewEmailAddressChangeRequestedAt());
    }

    public function test_send_email_verify_email_null_case(): void
    {
        $user = new class extends AbstractUser {
        };
        $user->setEmailAddressVerificationRequestedAt(new \DateTime());

        $factoryMock = $this->getFactoryFromContainerMock(VerifyEmailFactory::class);
        $factoryMock
            ->expects(self::once())
            ->method('create')
            ->with($user, self::TEST_CONTEXT)
            ->willReturn(null);

        $this->mailerMock->expects($this->never())->method('send');

        $result = $this->userMailer->sendEmailVerifyEmail($user);
        $this->assertFalse($result);
        $this->assertNull($user->getEmailAddressVerificationRequestedAt());
    }

    public function test_send_email_verify_email(): void
    {
        $user = new class extends AbstractUser {
        };

        $additionalExpectations = $this->createEmMockExpectation();

        $this->expectFactoryCallAndSendMailerMethod(VerifyEmailFactory::class, $user, $additionalExpectations);

        $result = $this->userMailer->sendEmailVerifyEmail($user);
        $this->assertTrue($result);
        $this->assertNotNull($user->getEmailAddressVerificationRequestedAt());
    }

    public function test_send_welcome_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_welcome_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(WelcomeEmailFactory::class, $user);

        $result = $this->userMailer->sendWelcomeEmail($user);
        $this->assertTrue($result);
    }

    public function test_send_user_enabled_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_user_enabled_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(UserEnabledEmailFactory::class, $user);

        $result = $this->userMailer->sendUserEnabledEmail($user);
        $this->assertTrue($result);
    }

    public function test_send_username_changed_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_username_changed_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(UsernameChangedEmailFactory::class, $user);

        $result = $this->userMailer->sendUsernameChangedEmail($user);
        $this->assertTrue($result);
    }

    public function test_send_password_changed_email(): void
    {
        $user = new class extends AbstractUser {
            protected ?string $username = 'test_send_password_changed_email';
        };

        $this->expectFactoryCallAndSendMailerMethod(PasswordChangedEmailFactory::class, $user);

        $result = $this->userMailer->sendPasswordChangedEmail($user);
        $this->assertTrue($result);
    }

    public static function afterWriteEmails(): iterable
    {
        yield 'welcome' => ['sendWelcomeEmail', WelcomeEmailFactory::class];
        yield 'user enabled' => ['sendUserEnabledEmail', UserEnabledEmailFactory::class];
        yield 'username changed' => ['sendUsernameChangedEmail', UsernameChangedEmailFactory::class];
        yield 'password changed' => ['sendPasswordChangedEmail', PasswordChangedEmailFactory::class];
        yield 'email verify' => ['sendEmailVerifyEmailAfterWrite', VerifyEmailFactory::class];
        yield 'change email confirmation' => ['sendChangeEmailConfirmationEmailAfterWrite', ChangeEmailConfirmationEmailFactory::class];
    }

    public static function jobEmails(): iterable
    {
        yield 'password reset' => ['sendPasswordResetEmail', PasswordResetEmailFactory::class];
        yield 'email verify' => ['sendEmailVerifyEmail', VerifyEmailFactory::class];
        yield 'change email confirmation' => ['sendChangeEmailConfirmationEmail', ChangeEmailConfirmationEmailFactory::class];
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('afterWriteEmails')]
    public function test_an_email_after_a_write_whose_link_is_refused_is_logged_and_not_sent(string $method, string $factoryClass): void
    {
        $user = $this->createNamedUser();
        $exception = new DisallowedRequestOriginException('refused');
        $handler = new TestHandler();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $this->mailerMock->expects(self::never())->method('send');

        $userMailer = $this->createMailerWithServices([
            $factoryClass => $this->createRefusingFactory($exception),
            'doctrine.orm.entity_manager' => $entityManager,
            'logger' => new Logger('test', [$handler]),
        ]);

        self::assertFalse($userMailer->{$method}($user));
        $records = $handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Error, $records[0]->level);
        self::assertSame('The email to the user `refused_user` was not sent: refused', $records[0]->message);
        self::assertSame(['user' => 'refused_user', 'exception' => $exception], $records[0]->context);
        self::assertNull($user->getEmailAddressVerificationRequestedAt());
        self::assertNull($user->getNewEmailAddressChangeRequestedAt());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('afterWriteEmails')]
    public function test_an_email_after_a_write_whose_link_is_refused_is_not_sent_without_a_logger(string $method, string $factoryClass): void
    {
        $this->mailerMock->expects(self::never())->method('send');
        $userMailer = $this->createMailerWithServices([
            $factoryClass => $this->createRefusingFactory(new DisallowedRequestOriginException('refused')),
        ]);

        self::assertFalse($userMailer->{$method}($this->createNamedUser()));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('jobEmails')]
    public function test_an_email_that_is_the_job_of_the_request_throws_when_its_link_is_refused(string $method, string $factoryClass): void
    {
        $exception = new DisallowedRequestOriginException('refused');
        $handler = new TestHandler();
        $this->mailerMock->expects(self::never())->method('send');
        $userMailer = $this->createMailerWithServices([
            $factoryClass => $this->createRefusingFactory($exception),
            'logger' => new Logger('test', [$handler]),
        ]);

        try {
            $userMailer->{$method}($this->createNamedUser());
            self::fail('The refused link was not thrown');
        } catch (DisallowedRequestOriginException $thrown) {
            self::assertSame($exception, $thrown);
        }
        self::assertSame([], $handler->getRecords());
    }

    public function test_an_email_verification_after_a_write_is_sent(): void
    {
        $user = $this->createNamedUser();
        $this->expectFactoryCallAndSendMailerMethod(VerifyEmailFactory::class, $user, $this->createEmMockExpectation());

        self::assertTrue($this->userMailer->sendEmailVerifyEmailAfterWrite($user));
        self::assertNotNull($user->getEmailAddressVerificationRequestedAt());
    }

    public function test_an_email_change_confirmation_after_a_write_is_sent(): void
    {
        $user = $this->createNamedUser();
        $this->expectFactoryCallAndSendMailerMethod(ChangeEmailConfirmationEmailFactory::class, $user, $this->createEmMockExpectation());

        self::assertTrue($this->userMailer->sendChangeEmailConfirmationEmailAfterWrite($user));
        self::assertNotNull($user->getNewEmailAddressChangeRequestedAt());
    }

    private function createNamedUser(): AbstractUser
    {
        $user = new class extends AbstractUser {
        };
        $user->setUsername('refused_user');

        return $user;
    }

    private function createRefusingFactory(\Throwable $exception): AbstractUserEmailFactory
    {
        $factory = $this->createStub(AbstractUserEmailFactory::class);
        $factory->method('create')->willThrowException($exception);

        return $factory;
    }

    /**
     * @param array<string, object> $services
     */
    private function createMailerWithServices(array $services): UserMailer
    {
        return new UserMailer($this->mailerMock, new ServiceLocator(array_map(static fn (object $service): \Closure => static fn (): object => $service, $services)), self::TEST_CONTEXT);
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
                ['doctrine.orm.entity_manager'],
                $emMock,
            ],
        ];
    }

    private function getFactoryFromContainerMock(string $factory, array $additionalExpectations = []): MockObject
    {
        $factoryMock = $this->createMock(AbstractUserEmailFactory::class);
        $expectations = [
            [[$factory], $factoryMock],
            ...$additionalExpectations,
        ];

        $invokedCount = self::exactly(\count($expectations));

        $this->containerMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$parameters) use ($invokedCount, $expectations) {
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
