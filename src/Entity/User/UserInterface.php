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

namespace Silverback\ApiComponentBundle\Entity\User;

use Serializable;
use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;

interface UserInterface extends Serializable, BaseUserInterface
{
    public function getId(): ?int;

    public function getUsername(): ?string;

    /**
     * @return static
     */
    public function setUsername(?string $username);

    public function getPassword(): string;

    /**
     * @return static
     */
    public function setPassword(string $password);

    public function getRoles(): array;

    /**
     * @return static
     */
    public function setRoles(?array $roles);

    public function isEnabled(): bool;

    public function getPlainPassword(): ?string;

    /**
     * @return static
     */
    public function setPlainPassword(?string $plainPassword);

    public function getPasswordResetConfirmationToken(): ?string;

    /**
     * @return static
     */
    public function setPasswordResetConfirmationToken(?string $passwordResetConfirmationToken);

    public function getPasswordRequestedAt(): ?\DateTime;

    /**
     * @return static
     */
    public function setPasswordRequestedAt(?\DateTime $passwordRequestedAt);

    public function isPasswordRequestLimitReached($ttl);

    public function getNewUsername(): ?string;

    /**
     * @return static
     */
    public function setNewUsername(?string $newUsername);

    public function getUsernameConfirmationToken(): ?string;

    /**
     * @return static
     */
    public function setUsernameConfirmationToken(?string $usernameConfirmationToken);

    public function getOldPassword(): ?string;

    public function setOldPassword(?string $oldPassword): void;

    /** @see \Serializable::serialize() */
    public function serialize(): string;

    /**
     * @param string $serialized
     *
     * @see \Serializable::unserialize()
     */
    public function unserialize($serialized): void;

    public function getSalt();

    public function eraseCredentials(): void;
}
