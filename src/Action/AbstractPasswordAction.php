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

use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\PasswordManager;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractPasswordAction
{
    protected UserRepository $userRepository;
    protected PasswordManager $passwordManager;

    public function __construct(
        UserRepository $userRepository,
        PasswordManager $passwordManager
    ) {
        $this->userRepository = $userRepository;
        $this->passwordManager = $passwordManager;
    }
}
