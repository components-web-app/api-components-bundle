<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<AbstractUser>
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UserStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedException('Access denied.');
        }

        if (!$user instanceof AbstractUser) {
            throw new AccessDeniedException('Access denied. User not supported.');
        }

        $username = $user->getUsername();
        if (!$username) {
            return null;
        }

        $storedUser = $this->userRepository->loadUserByIdentifier($username);
        if (null === $storedUser) {
            throw new UnauthorizedHttpException('Bearer', 'User not found.');
        }

        return $storedUser;
    }
}
