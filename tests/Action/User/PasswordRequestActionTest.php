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
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Exception\DisallowedRequestOriginException;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PasswordRequestActionTest extends TestCase
{
    public function test_a_refused_email_link_discards_the_new_token_without_saving_it(): void
    {
        $user = new User();
        $calls = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::once())->method('refresh')->with($user)->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'refresh';
        });
        $mailer = $this->createStub(UserMailer::class);
        $mailer->method('sendPasswordResetEmail')->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'send';
            throw new DisallowedRequestOriginException('refused');
        });

        $response = $this->createAction($user, $entityManager, $mailer)(new Request(), 'my_username');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['send', 'refresh'], $calls);
    }

    public function test_the_new_token_is_saved_only_after_the_email_was_built(): void
    {
        $user = new User();
        $calls = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('refresh');
        $entityManager->expects(self::once())->method('flush')->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'flush';
        });
        $mailer = $this->createStub(UserMailer::class);
        $mailer->method('sendPasswordResetEmail')->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'send';

            return true;
        });

        $response = $this->createAction($user, $entityManager, $mailer)(new Request(), 'my_username');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['send', 'flush'], $calls);
    }

    public function test_an_email_that_fails_to_send_is_a_service_unavailable_response_with_the_token_saved(): void
    {
        $user = new User();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $mailer = $this->createStub(UserMailer::class);
        $mailer->method('sendPasswordResetEmail')->willReturn(false);

        $response = $this->createAction($user, $entityManager, $mailer)(new Request(), 'my_username');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    private function createAction(User $user, EntityManagerInterface $entityManager, UserMailer $mailer): PasswordRequestAction
    {
        $userDataProcessor = $this->createStub(UserDataProcessor::class);
        $userDataProcessor->method('updatePasswordConfirmationToken')->willReturn($user);

        return new PasswordRequestAction($userDataProcessor, $entityManager, $mailer);
    }
}
