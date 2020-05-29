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

namespace Silverback\ApiComponentsBundle\RefreshToken;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
class RefreshToken
{
    protected ?\DateTimeInterface $createdAt = null;
    protected ?\DateTimeInterface $expiredAt = null;
    protected ?UserInterface $user = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getExpiredAt(): ?\DateTimeInterface
    {
        return $this->expiredAt;
    }

    public function setExpiredAt(\DateTimeInterface $expiredAt): void
    {
        $this->expiredAt = $expiredAt;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() < $this->expiredAt;
    }
}
