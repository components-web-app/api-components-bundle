<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Realtime;

use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class RealtimeUserResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private IriConverterInterface $iriConverter,
    ) {
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function resolve(): ?array
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof AbstractUser) {
            return ['id' => $this->iriConverter->getIriFromResource($user), 'name' => $user->getUsername()];
        }

        if ($user instanceof UserInterface) {
            return ['id' => $user->getUserIdentifier(), 'name' => $user->getUserIdentifier()];
        }

        return null;
    }
}
