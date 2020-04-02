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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordRequestAction extends AbstractPasswordAction
{
    public function __invoke(Request $request, string $username): Response
    {
        try {
            $this->passwordManager->requestResetEmail($username);
        } catch (NotFoundHttpException $exception) {
            return $this->getResponse($request, null, Response::HTTP_NOT_FOUND);
        }

        return $this->getResponse($request);
    }
}
