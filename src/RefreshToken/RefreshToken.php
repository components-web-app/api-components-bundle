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
    protected ?\DateTimeInterface $expiresAt = null;
    protected ?UserInterface $user = null;
    protected ?int $version = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @return static
     */
    public function setCreatedAt(\DateTimeInterface $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * @return static
     */
    public function setExpiresAt(\DateTimeInterface $expiresAt)
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    /**
     * @return static
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        return $this;
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }
}
