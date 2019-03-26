<?php

namespace Silverback\ApiComponentBundle\Entity\User;

use Silverback\ApiComponentBundle\Validator\Constraints as APIAssert;
use Doctrine\ORM\Mapping as ORM;
use Serializable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\User\UserRepository")
 * @UniqueEntity(fields={"username"}, message="A user is already registered with that email address as their username")
 * @APIAssert\NewUsername(groups={"new_username", "Default"})
 */
class User implements Serializable, UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank(groups={"Default"})
     * @Assert\Email(groups={"Default"})
     * @Groups({"default"})
     * @var string|null
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"default"})
     */
    private $password;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"default"})
     */
    private $enabled;

    /**
     * @ORM\Column(type="array")
     * @Groups({"default"})
     */
    private $roles;

    /**
     * @Assert\NotBlank(message="Please enter your desired password", groups={"Default", "password_reset", "change_password"})
     * @Assert\Length(max="4096",min="6",maxMessage="Your password cannot be over 4096 characters",minMessage="Your password must be more than 6 characters long", groups={"Default", "password_reset", "change_password"})
     * @Groups({"default"})
     * @var string|null
     */
    private $plainPassword;

    /**
     * Random string sent to the user email address in order to verify it.
     * @ORM\Column(nullable=true)
     * @Groups({"default"})
     * @var string|null
     */
    private $passwordResetConfirmationToken;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"default"})
     * @var \DateTime|null
     */
    private $passwordRequestedAt;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\NotBlank(groups={"new_username"})
     * @Assert\Email(groups={"new_username"})
     * @Groups({"default"})
     * @var string|null
     */
    private $newUsername;

    /**
     * Random string sent to the user's new email address in order to verify it.
     * @ORM\Column(nullable=true)
     * @Groups({"default"})
     * @var string|null
     */
    private $usernameConfirmationToken;

    /**
     * @UserPassword(message="You have not entered your current password correctly. Please try again.", groups={"change_password"})
     * @Groups({"default"})
     * @var string|null
     */
    private $oldPassword;

    public function __construct(
        string $username = '',
        array $roles = [ 'ROLE_USER' ],
        string $password = '',
        bool $enabled = true
    ) {
        $this->username = $username;
        $this->roles = $roles;
        $this->password = $password;
        $this->enabled = $enabled;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string|null $username
     * @return static
     */
    public function setUsername(?string $username)
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return static
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param array|null $roles
     * @return static
     */
    public function setRoles(?array $roles)
    {
        $this->roles = $roles;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return null|string
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * @param null|string $plainPassword
     * @return static
     */
    public function setPlainPassword(?string $plainPassword)
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getPasswordResetConfirmationToken(): ?string
    {
        return $this->passwordResetConfirmationToken;
    }

    /**
     * @param null|string $passwordResetConfirmationToken
     * @return static
     */
    public function setPasswordResetConfirmationToken(?string $passwordResetConfirmationToken)
    {
        $this->passwordResetConfirmationToken = $passwordResetConfirmationToken;

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getPasswordRequestedAt(): ?\DateTime
    {
        return $this->passwordRequestedAt;
    }

    /**
     * @param \DateTime|null $passwordRequestedAt
     * @return static
     */
    public function setPasswordRequestedAt(?\DateTime $passwordRequestedAt)
    {
        $this->passwordRequestedAt = $passwordRequestedAt;

        return $this;
    }

    public function isPasswordRequestLimitReached($ttl)
    {
        $lastRequest = $this->getPasswordRequestedAt();
        return $lastRequest instanceof \DateTime &&
            $lastRequest->getTimestamp() + $ttl > time();
    }

    /**
     * @return null|string
     */
    public function getNewUsername(): ?string
    {
        return $this->newUsername;
    }

    /**
     * @param null|string $newUsername
     * @return static
     */
    public function setNewUsername(?string $newUsername)
    {
        $this->newUsername = $newUsername;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getUsernameConfirmationToken(): ?string
    {
        return $this->usernameConfirmationToken;
    }

    /**
     * @param null|string $usernameConfirmationToken
     * @return static
     */
    public function setUsernameConfirmationToken(?string $usernameConfirmationToken)
    {
        $this->usernameConfirmationToken = $usernameConfirmationToken;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getOldPassword(): ?string
    {
        return $this->oldPassword;
    }

    /**
     * @param null|string $oldPassword
     */
    public function setOldPassword(?string $oldPassword): void
    {
        $this->oldPassword = $oldPassword;
    }

    /** @see \Serializable::serialize() */
    public function serialize(): string
    {
        return serialize([
            $this->id,
            $this->username,
            $this->password,
            $this->enabled
        ]);
    }

    /**
     * @see \Serializable::unserialize()
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        [
            $this->id,
            $this->username,
            $this->password,
            $this->enabled
        ] = unserialize($serialized, ['allowed_classes' => false]);
    }

    // Not needed - we use bcrypt
    public function getSalt()
    {
    }

    // remove sensitive data - e.g. plain passwords etc.
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function __toString()
    {
        return (string) $this->id;
    }
}
