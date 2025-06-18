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

namespace Silverback\ApiComponentsBundle\Action\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ResendVerifyEmailAddressAction
{
    public function __construct(
        private UserMailer $userMailer,
        private UserDataProcessor $userDataProcessor,
        private Security $security,
    ) {
    }

    public function __invoke(string $username, string $token): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof AbstractUser) {
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }
        $this->userDataProcessor->setEmailAddressVerifyToken($user);
        $emailSuccess = $this->userMailer->sendEmailVerifyEmail($user);

        $response = new Response(null, $emailSuccess ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
        $response->setCache([
            'private' => true,
            's_maxage' => 0,
            'max_age' => 0,
        ]);

        return $response;
    }
}
