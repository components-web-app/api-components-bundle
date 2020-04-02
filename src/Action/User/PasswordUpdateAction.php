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

namespace Silverback\ApiComponentBundle\Action\User;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateAction extends AbstractPasswordAction
{
    public function __invoke(Request $request)
    {
        $data = $this->serializer->decode($request->getContent(), $this->getFormat($request), []);
        $requiredKeys = ['username', 'token', 'password'];
        foreach ($requiredKeys as $requiredKey) {
            if (!isset($data[$requiredKey])) {
                throw new BadRequestHttpException(sprintf('the key `%s` was not found in POST data', $requiredKey));
            }
        }

        try {
            $this->passwordManager->passwordReset($data['username'], $data['token'], $data['password']);

            return $this->getResponse($request);
        } catch (AuthenticationException $exception) {
            $errors = $exception->getMessageData();

            return $this->getResponse($request, $errors, Response::HTTP_BAD_REQUEST);
        } catch (NotFoundHttpException $exception) {
            return $this->getResponse($request, $exception->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }
}
