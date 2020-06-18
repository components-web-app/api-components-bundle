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

namespace Silverback\ApiComponentsBundle\Entity\User;

use ApiPlatform\Core\Annotation\ApiProperty;
use DateTime;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Validator\Constraints as AcbAssert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @UniqueEntity(fields={"username"}, errorPath="username", message="Sorry, that user already exists in the database.")
 * @UniqueEntity(fields={"emailAddress"}, errorPath="emailAddress", message="Sorry, that email address already exists in the database.")
 * @AcbAssert\NewEmailAddress(groups={"User:emailAddress", "Default"})
 */
abstract class AbstractUser implements SymfonyUserInterface, JWTUserInterface
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotBlank(groups={"Default"}, message="Please enter a username.")
     * @Groups({"User:superAdmin", "User:output", "Form:component:read"})
     */
    protected ?string $username;

    /**
     * @Assert\NotBlank(groups={"Default"})
     * @Assert\Email()
     * @Groups({"User:superAdmin", "User:output", "Form:component:read"})
     */
    protected ?string $emailAddress;

    /**
     * @Groups({"User:superAdmin", "User:output", "Form:component:read"})
     */
    protected array $roles;

    /**
     * @Groups({"User:superAdmin"})
     */
    protected bool $enabled;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    protected string $password;

    /**
     * @ApiProperty(readable=false)
     * @Assert\NotBlank(message="Please enter your desired password.", groups={"User:password:create"})
     * @Assert\Length(max="4096", min="6", maxMessage="Your password cannot be over 4096 characters", minMessage="Your password must be more than 6 characters long.", groups={"User:password:create"})
     * @Groups({"User:input"})
     */
    protected ?string $plainPassword = null;

    /**
     * Random string sent to the user email address in order to verify it.
     *
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?string $newPasswordConfirmationToken = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    public ?string $plainNewPasswordConfirmationToken = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?DateTime $passwordRequestedAt = null;

    /**
     * @ApiProperty(readable=false)
     * @UserPassword(message="You have not entered your current password correctly. Please try again.", groups={"User:password:change"})
     * @Groups({"User:input"})
     */
    protected ?string $oldPassword = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?DateTime $passwordUpdatedAt = null;

    /**
     * @Assert\NotBlank(groups={"User:emailAddress", "Default"}, allowNull=true)
     * @Assert\Email()
     * @Groups({"User:input", "User:output", "User:emailAddress", "Form:component:read:role_user"})
     */
    protected ?string $newEmailAddress = null;

    /**
     * Random string sent to the user's new email address in order to verify it.
     *
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?string $newEmailConfirmationToken = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     * @Groups({"User:output"})
     */
    protected ?DateTime $newEmailAddressChangeRequestedAt = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    public ?string $plainNewEmailConfirmationToken = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    protected bool $emailAddressVerified = false;

    /**
     * Random string sent to previous email address when email is changed to permit email restore and password change.
     *
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?string $emailAddressVerifyToken = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    public ?string $plainEmailAddressVerifyToken = null;

    /**
     * @ApiProperty(readable=false, writable=false)
     */
    protected ?DateTime $emailLastUpdatedAt = null;

    public function __construct(string $username = '', string $emailAddress = '', bool $emailAddressVerified = false, array $roles = ['ROLE_USER'], string $password = '', bool $enabled = true)
    {
        $this->username = $username;
        $this->emailAddress = $emailAddress;
        $this->emailAddressVerified = $emailAddressVerified;
        $this->roles = $roles;
        $this->password = $password;
        $this->enabled = $enabled;
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
        if ($emailAddress) {
            $this->emailLastUpdatedAt = new \DateTime();
        }

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
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
            $this->passwordUpdatedAt = new \DateTime();
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
        if ($newEmailAddress) {
            $this->newEmailAddressChangeRequestedAt = new \DateTime();
        }

        return $this;
    }

    public function getNewEmailConfirmationToken(): ?string
    {
        return $this->newEmailConfirmationToken;
    }

    public function setNewEmailConfirmationToken(?string $newEmailConfirmationToken): self
    {
        $this->newEmailConfirmationToken = $newEmailConfirmationToken;

        return $this;
    }

    public function getNewEmailAddressChangeRequestedAt(): ?DateTime
    {
        return $this->newEmailAddressChangeRequestedAt;
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

    public function getEmailAddressVerifyToken(): ?string
    {
        return $this->emailAddressVerifyToken;
    }

    public function setEmailAddressVerifyToken(?string $emailAddressVerifyToken): void
    {
        $this->emailAddressVerifyToken = $emailAddressVerifyToken;
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
        return serialize(
            [
                (string) $this->id,
                $this->username,
                $this->emailAddress,
                $this->password,
                $this->enabled,
                $this->roles,
            ]
        );
    }

    /**
     * @see \Serializable::unserialize()
     */
    public function unserialize(string $serialized): self
    {
        $id = null;
        [
            $id,
            $this->username,
            $this->emailAddress,
            $this->password,
            $this->enabled,
            $this->roles,
        ] = unserialize($serialized, ['allowed_classes' => false]);
        $this->id = Uuid::fromString($id);

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
        return (string) $this->id;
    }

    public static function createFromPayload($username, array $payload)
    {
        $newUser = new static(
            $username,
            $payload['emailAddress'],
            $payload['emailAddressVerified'],
            $payload['roles']
        );

        $newUser->setNewEmailAddress($payload['newEmailAddress']);

        $reflection = new \ReflectionClass(static::class);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($newUser, Uuid::fromString($payload['id']));

        return $newUser;
    }
}
