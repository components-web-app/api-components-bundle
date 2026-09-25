<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Action\User;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\RequestLimitReachedException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ResendVerifyEmailAddressAction
{
    public function __construct(
        private UserMailer $userMailer,
        private UserDataProcessor $userDataProcessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(string $username): Response
    {
        try {
            $user = $this->userDataProcessor->updateVerifyEmailToken($username);
        } catch (InvalidArgumentException) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        } catch (RequestLimitReachedException $exception) {
            return UserEmailResponse::tooManyRequests($exception);
        }

        try {
            $emailSuccess = $this->userMailer->sendEmailVerifyEmail($user);
        } catch (UnparseableRequestHeaderException) {
            $this->entityManager->refresh($user);

            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if (!$emailSuccess) {
            $this->entityManager->refresh($user);
        }

        return UserEmailResponse::sent($emailSuccess);
    }
}
