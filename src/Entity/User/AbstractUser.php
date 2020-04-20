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

use ApiPlatform\Core\Annotation\ApiProperty;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentBundle\Validator\Constraints as APIAssert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\MappedSuperclass(repositoryClass="Silverback\ApiComponentBundle\Repository\User\UserRepository")
 * @UniqueEntity(fields={"username"}, errorPath="username", message="Sorry, that user already exists in the database.")
 * @APIAssert\NewEmailAddress(groups={"new_email_address", "Default"})
 */
abstract class AbstractUser implements SymfonyUserInterface, TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank(groups={"Default"})
     * @Groups({"User:super_admin", "User:output"})
     */
    protected ?string $username;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank(groups={"Default"})
     * @Assert\Email()
     * @Groups({"User:super_admin", "User:output"})
     */
    protected ?string $emailAddress;

    /**
     * @ORM\Column(type="array")
     * @Groups({"User:super_admin"})
     */
    protected array $roles;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"User:super_admin"})
     */
    protected bool $enabled;

    /**
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(readable=false, writable=false)
     */
    protected string $password;

    /**
     * @ApiProperty(readable=false)
     * @Assert\NotBlank(message="Please enter your desired password", groups={"password_reset", "change_password"})
     * @Assert\Length(max="4096", min="6", maxMessage="Your password cannot be over 4096 characters", minMessage="Your password must be more than 6 characters long", groups={"Default", "password_reset", "change_password"})
     * @Groups({"User:input"})
     */
    protected ?string $plainPassword = null;

    /**
     * Random string sent to the user email address in order to verify it.
     *
     * @ORM\Column(nullable=true)
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?string $newPasswordConfirmationToken = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?DateTime $passwordRequestedAt = null;

    /**
     * @ApiProperty(readable=false)
     * @UserPassword(message="You have not entered your current password correctly. Please try again.", groups={"change_password"})
     * @Groups({"User:input"})
     */
    protected ?string $oldPassword = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $passwordLastUpdated = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\NotBlank(groups={"new_email_address"})
     * @Groups({"User:input", "User:output", "new_email_address"})
     */
    protected ?string $newEmailAddress = null;

    /**
     * Random string sent to the user's new email address in order to verify it.
     *
     * @ORM\Column(nullable=true)
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?string $newEmailVerificationToken = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     * @ApiProperty(readable=false, writable=false)
     */
    protected bool $emailAddressVerified = false;

    public function __construct(
        string $username = '',
        string $emailAddress = '',
        bool $emailAddressVerified = false,
        array $roles = ['ROLE_USER'],
        string $password = '',
        bool $enabled = true
    ) {
        $this->username = $username;
        $this->emailAddress = $emailAddress;
        $this->emailAddressVerified = $emailAddressVerified;
        $this->roles = $roles;
        $this->password = $password;
        $this->enabled = $enabled;
        $this->setId();
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(?string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(?array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        if ($plainPassword) {
            // Needs to update mapped field to trigger update event which will encode the plain password
            $this->passwordLastUpdated = new \DateTime();
        }

        return $this;
    }

    public function getNewPasswordConfirmationToken(): ?string
    {
        return $this->newPasswordConfirmationToken;
    }

    public function setNewPasswordConfirmationToken(?string $newPasswordConfirmationToken): self
    {
        $this->newPasswordConfirmationToken = $newPasswordConfirmationToken;

        return $this;
    }

    public function getPasswordRequestedAt(): ?DateTime
    {
        return $this->passwordRequestedAt;
    }

    public function setPasswordRequestedAt(?DateTime $passwordRequestedAt): self
    {
        $this->passwordRequestedAt = $passwordRequestedAt;

        return $this;
    }

    public function getOldPassword(): ?string
    {
        return $this->oldPassword;
    }

    public function setOldPassword(?string $oldPassword): self
    {
        $this->oldPassword = $oldPassword;

        return $this;
    }

    public function getNewEmailAddress(): ?string
    {
        return $this->newEmailAddress;
    }

    public function setNewEmailAddress(?string $newEmailAddress): self
    {
        $this->newEmailAddress = $newEmailAddress;

        return $this;
    }

    public function getNewEmailVerificationToken(): ?string
    {
        return $this->newEmailVerificationToken;
    }

    public function setNewEmailVerificationToken(?string $newEmailVerificationToken): self
    {
        $this->newEmailVerificationToken = $newEmailVerificationToken;

        return $this;
    }

    public function isEmailAddressVerified(): bool
    {
        return $this->emailAddressVerified;
    }

    public function setEmailAddressVerified(bool $emailAddressVerified): self
    {
        $this->emailAddressVerified = $emailAddressVerified;

        return $this;
    }

    public function isPasswordRequestLimitReached($ttl): bool
    {
        $lastRequest = $this->getPasswordRequestedAt();

        return $lastRequest instanceof DateTime &&
            $lastRequest->getTimestamp() + $ttl > time();
    }

    /** @see \Serializable::serialize() */
    public function serialize(): string
    {
        return serialize([
            $this->id,
            $this->username,
            $this->emailAddress,
            $this->password,
            $this->enabled,
            $this->roles,
        ]);
    }

    /**
     * @see \Serializable::unserialize()
     */
    public function unserialize(string $serialized): self
    {
        [
            $this->id,
            $this->username,
            $this->emailAddress,
            $this->password,
            $this->enabled,
            $this->roles,
        ] = unserialize($serialized, ['allowed_classes' => false]);

        return $this;
    }

    /**
     * Not needed - we use bcrypt.
     *
     * @ApiProperty(readable=false, writable=false)
     */
    public function getSalt()
    {
    }

    /**
     * Remove sensitive data - e.g. plain passwords etc.
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function __toString()
    {
        return $this->id;
    }
}
