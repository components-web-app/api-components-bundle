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
use Symfony\Component\Routing\Annotation\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordRequestAction extends AbstractPasswordAction
{
    /**
     * @Route("/request/{username}", name="password_reset_request", methods={"get"})
     */
    public function __invoke(Request $request, string $username)
    {
        $user = $this->userRepository->findOneBy(['email' => $username]);
        if (!$user) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }
        $this->passwordManager->requestResetEmail($user);

        return new JsonResponse([], Response::HTTP_OK);
    }
}
