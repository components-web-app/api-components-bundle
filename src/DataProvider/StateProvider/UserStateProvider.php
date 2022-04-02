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

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserStateProvider implements ProviderInterface
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function provide(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = [])
    {
        return $this->userRepository->loadUserByIdentifier($uriVariables['id']);
    }

    public function supports(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = []): bool
    {
        /** @var Operation */
        $operation = $context['operation'];
        return 'me' === $operationName && !$operation->isCollection() &&
            is_a($resourceClass, AbstractUser::class, true);
    }
}
