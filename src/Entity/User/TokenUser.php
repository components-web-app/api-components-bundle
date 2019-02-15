<?php

namespace Silverback\ApiComponentBundle\Entity\User;

use Symfony\Component\Security\Core\User\UserInterface;

class TokenUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_TOKEN_USER'];
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getUsername(): string
    {
        return 'token_user';
    }

    public function eraseCredentials(): void
    {
    }
}
