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

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ResendVerifyNewEmailAddressAction
{
    public function __construct(
        private UserMailer $userMailer,
        private UserDataProcessor $userDataProcessor,
    ) {
    }

    public function __invoke(string $username): Response
    {
        try {
            $user = $this->userDataProcessor->updateNewEmailToken($username);
        } catch (InvalidArgumentException $e) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        if (!$user) {
            $response = new Response(null, Response::HTTP_OK);
            $response->setCache([
                'private' => true,
                's_maxage' => 0,
                'max_age' => 0,
            ]);

            return $response;
        }
        try {
            $emailSuccess = $this->userMailer->sendChangeEmailConfirmationEmail($user);
        } catch (UnparseableRequestHeaderException) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $response = new Response(null, $emailSuccess ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
        $response->setCache([
            'private' => true,
            's_maxage' => 0,
            'max_age' => 0,
        ]);

        return $response;
    }
}
