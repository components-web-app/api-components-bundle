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

use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Helper\User\PasswordManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordRequestAction
{
    private PasswordManager $passwordManager;

    public function __construct(PasswordManager $passwordManager)
    {
        $this->passwordManager = $passwordManager;
    }

    public function __invoke(Request $request, string $username)
    {
        try {
            $this->passwordManager->requestResetEmail($username);

            return new Response(null, Response::HTTP_OK);
        } catch (UnexpectedValueException $e) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }
    }
}
