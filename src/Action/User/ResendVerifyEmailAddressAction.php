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
    ) {
    }

    public function __invoke(string $username): Response
    {
        $user = $this->userDataProcessor->updateVerifyEmailToken($username);
        if (!$user) {
            $response = new Response(null, Response::HTTP_OK);
            $response->setCache([
                'private' => true,
                's_maxage' => 0,
                'max_age' => 0,
            ]);

            return $response;
        }

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
