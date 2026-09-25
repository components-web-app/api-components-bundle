<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Action\User;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyEmailAddressAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyNewEmailAddressAction;
use Silverback\ApiComponentsBundle\Exception\DisallowedRequestOriginException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\RequestLimitReachedException;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\HttpFoundation\Response;

class ResendEmailActionTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function actions(): iterable
    {
        yield 'email verification' => [ResendVerifyEmailAddressAction::class, 'updateVerifyEmailToken', 'sendEmailVerifyEmail'];
        yield 'new email confirmation' => [ResendVerifyNewEmailAddressAction::class, 'updateNewEmailToken', 'sendChangeEmailConfirmationEmail'];
    }

    #[DataProvider('actions')]
    public function test_a_throttled_request_is_too_many_requests_with_the_seconds_to_wait_and_sends_nothing(string $actionClass, string $processorMethod, string $mailerMethod): void
    {
        $processor = $this->createStub(UserDataProcessor::class);
        $processor->method($processorMethod)->willThrowException(new RequestLimitReachedException(42));
        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::never())->method($mailerMethod);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $response = (new $actionClass($mailer, $processor, $entityManager))('my_username');

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('42', $response->headers->get('Retry-After'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    #[DataProvider('actions')]
    public function test_an_unknown_user_is_not_found(string $actionClass, string $processorMethod, string $mailerMethod): void
    {
        $processor = $this->createStub(UserDataProcessor::class);
        $processor->method($processorMethod)->willThrowException(new InvalidArgumentException('Username not found'));

        $response = (new $actionClass($this->createStub(UserMailer::class), $processor, $this->createStub(EntityManagerInterface::class)))('no_user');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[DataProvider('actions')]
    public function test_a_sent_email_is_ok_and_keeps_the_new_token(string $actionClass, string $processorMethod, string $mailerMethod): void
    {
        $user = new User();
        $processor = $this->createStub(UserDataProcessor::class);
        $processor->method($processorMethod)->willReturn($user);
        $mailer = $this->createStub(UserMailer::class);
        $mailer->method($mailerMethod)->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('refresh');

        $response = (new $actionClass($mailer, $processor, $entityManager))('my_username');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    #[DataProvider('actions')]
    public function test_an_email_that_fails_to_send_is_a_service_unavailable_response_with_nothing_saved(string $actionClass, string $processorMethod, string $mailerMethod): void
    {
        $user = new User();
        $processor = $this->createStub(UserDataProcessor::class);
        $processor->method($processorMethod)->willReturn($user);
        $mailer = $this->createStub(UserMailer::class);
        $mailer->method($mailerMethod)->willReturn(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::once())->method('refresh')->with($user);

        $response = (new $actionClass($mailer, $processor, $entityManager))('my_username');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    #[DataProvider('actions')]
    public function test_a_refused_email_link_is_a_bad_request_with_nothing_saved(string $actionClass, string $processorMethod, string $mailerMethod): void
    {
        $user = new User();
        $processor = $this->createStub(UserDataProcessor::class);
        $processor->method($processorMethod)->willReturn($user);
        $mailer = $this->createStub(UserMailer::class);
        $mailer->method($mailerMethod)->willThrowException(new DisallowedRequestOriginException('refused'));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::once())->method('refresh')->with($user);

        $response = (new $actionClass($mailer, $processor, $entityManager))('my_username');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
