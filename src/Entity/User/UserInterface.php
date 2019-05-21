<?php

namespace Silverback\ApiComponentBundle\Entity\User;

use Serializable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;

/**
 * @ORM\MappedSuperclass(repositoryClass="Silverback\ApiComponentBundle\Repository\User\UserRepository")
 * @UniqueEntity(fields={"username"}, message="A user is already registered with that email address as their username")
 * @APIAssert\NewUsername(groups={"new_username", "Default"})
 */
interface UserInterface extends Serializable, BaseUserInterface
{
    public function getId(): ?int;

    public function getUsername(): ?string;

    /**
     * @param string|null $username
     * @return static
     */
    public function setUsername(?string $username);

    public function getPassword(): string;

    /**
     * @param string $password
     * @return static
     */
    public function setPassword(string $password);

    public function getRoles(): array;

    /**
     * @param array|null $roles
     * @return static
     */
    public function setRoles(?array $roles);

    public function isEnabled(): bool;

    /**
     * @return null|string
     */
    public function getPlainPassword(): ?string;

    /**
     * @param null|string $plainPassword
     * @return static
     */
    public function setPlainPassword(?string $plainPassword);

    /**
     * @return null|string
     */
    public function getPasswordResetConfirmationToken(): ?string;

    /**
     * @param null|string $passwordResetConfirmationToken
     * @return static
     */
    public function setPasswordResetConfirmationToken(?string $passwordResetConfirmationToken);

    /**
     * @return \DateTime|null
     */
    public function getPasswordRequestedAt(): ?\DateTime;

    /**
     * @param \DateTime|null $passwordRequestedAt
     * @return static
     */
    public function setPasswordRequestedAt(?\DateTime $passwordRequestedAt);

    public function isPasswordRequestLimitReached($ttl);

    /**
     * @return null|string
     */
    public function getNewUsername(): ?string;

    /**
     * @param null|string $newUsername
     * @return static
     */
    public function setNewUsername(?string $newUsername);

    /**
     * @return null|string
     */
    public function getUsernameConfirmationToken(): ?string;

    /**
     * @param null|string $usernameConfirmationToken
     * @return static
     */
    public function setUsernameConfirmationToken(?string $usernameConfirmationToken);

    /**
     * @return null|string
     */
    public function getOldPassword(): ?string;

    /**
     * @param null|string $oldPassword
     */
    public function setOldPassword(?string $oldPassword): void;

    /** @see \Serializable::serialize() */
    public function serialize(): string;

    /**
     * @param string $serialized
     * @see \Serializable::unserialize()
     */
    public function unserialize($serialized): void;

    public function getSalt();

    public function eraseCredentials(): void;
}
