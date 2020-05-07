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

use Silverback\ApiComponentsBundle\Helper\User\PasswordManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateAction
{
    private PasswordManager $passwordManager;

    public function __construct(PasswordManager $passwordManager)
    {
        $this->passwordManager = $passwordManager;
    }

    public function __invoke(Request $request)
    {
        $requiredKeys = ['username', 'token', 'password'];
        foreach ($requiredKeys as $requiredKey) {
            if (!isset($data[$requiredKey])) {
                throw new BadRequestHttpException(sprintf('the key `%s` was not found in POST data', $requiredKey));
            }
        }
        $this->passwordManager->passwordReset($data['username'], $data['token'], $data['password']);
    }
}
