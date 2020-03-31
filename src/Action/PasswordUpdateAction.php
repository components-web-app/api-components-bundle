<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Action;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateAction extends AbstractPasswordAction
{
    public function __invoke(Request $request)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $username = $data['username'];
        $token = $data['token'];
        $user = $this->userRepository->findOneByPasswordResetToken(
            $username,
            $token
        );
        if (!$user) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->passwordManager->passwordReset($user, $data['password']);

            return new JsonResponse([], Response::HTTP_OK);
        } catch (AuthenticationException $exception) {
            $errors = $exception->getMessageData();

            return new JsonResponse($errors, Response::HTTP_BAD_REQUEST);
        }
    }
}
