<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\User;

use ApiPlatform\Test\Client;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RefreshToken;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CookieTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\EmailTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait UserTrait
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CookieTrait;
    use EmailTrait;
    use JsonTrait;
    use LogRecordsTrait;
    use ResourceTrait;

    protected static function generatedSecret(): string
    {
        return bin2hex(random_bytes(8));
    }

    protected static function createUserWithRole(string $username, string $password, string $role, string $emailAddress = 'test.user@example.com'): User
    {
        $user = static::createUser([$role], $username, $emailAddress, $password);
        static::rebootKernel();

        return $user;
    }

    protected static function loginAsUserWithPassword(Client $client, string $password): User
    {
        $user = static::createUser(['ROLE_USER'], password: $password);
        static::addBearerToken($client, $user);

        return $user;
    }

    /**
     * @param callable(User): void $change
     */
    protected static function changeUser(User $user, callable $change): void
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $stored = $manager->find(User::class, $user->getId());
        \assert($stored instanceof User);
        $change($stored);
        $manager->flush();
        static::rebootKernel();
    }

    protected static function hashToken(User $user, string $token): string
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $token);
    }

    protected static function disableUser(User $user): void
    {
        static::changeUser($user, static fn (User $stored) => $stored->setEnabled(false));
    }

    protected static function givePasswordResetToken(User $user, string $token, string $requestedAt): void
    {
        static::changeUser($user, static fn (User $stored) => $stored->setNewPasswordConfirmationToken(static::hashToken($stored, $token))->setPasswordRequestedAt(new \DateTime($requestedAt)));
    }

    protected static function unverifyEmail(User $user, ?string $verificationToken = null): void
    {
        static::changeUser($user, static function (User $stored) use ($verificationToken): void {
            $stored->setEmailAddressVerified(false);
            if ($verificationToken) {
                $stored->setEmailAddressVerifyToken(static::hashToken($stored, $verificationToken));
            }
        });
    }

    protected static function setEmailVerificationRequestedAt(User $user, string $requestedAt): void
    {
        static::changeUser($user, static fn (User $stored) => $stored->setEmailAddressVerificationRequestedAt(new \DateTime($requestedAt)));
    }

    protected static function giveNewEmailAddress(User $user, string $emailAddress, string $token, string $sentAt = 'now'): void
    {
        static::changeUser($user, static fn (User $stored) => $stored
            ->setNewEmailAddress($emailAddress)
            ->setNewEmailConfirmationToken(static::hashToken($stored, $token))
            ->setNewEmailAddressChangeRequestedAt(new \DateTime($sentAt)));
    }

    protected static function createForm(string $formType): string
    {
        $form = new Form();
        $form->formType = $formType;
        static::persistAndFlush($form);

        return static::iriOf($form);
    }

    protected static function findUserByUsername(string $username): ?User
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return $manager->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    protected static function countUsersWithUsername(string $username): int
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return \count($manager->getRepository(User::class)->findBy(['username' => $username]));
    }

    protected static function assertPasswordIs(string $password, string $identifier): void
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $user = $manager->getRepository(User::class)->loadUserByIdentifier($identifier);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $password), 'The password is not the expected one.');
    }

    protected static function assertUserTokenStillIs(string $tokenName, string $username, string $token): void
    {
        $user = static::findUserByUsername($username);
        self::assertNotNull($user, \sprintf('The user `%s` does not exist', $username));
        $storedToken = match ($tokenName) {
            'password reset' => $user->getNewPasswordConfirmationToken(),
            'email verification' => $user->getEmailAddressVerifyToken(),
            'new email confirmation' => $user->getNewEmailConfirmationToken(),
        };
        self::assertNotNull($storedToken, \sprintf('The %s token for the user `%s` is gone.', $tokenName, $username));
        self::assertTrue(static::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher($user)->verify($storedToken, $token), \sprintf('The %s token for the user `%s` has changed.', $tokenName, $username));
    }

    protected static function assertEmailAddressVerified(string $username, bool $verified = true): void
    {
        $user = static::findUserByUsername($username);
        self::assertNotNull($user);
        self::assertSame($verified, $user->isEmailAddressVerified());
    }

    protected static function assertEmailAddressConfirmed(string $emailAddress, string $username): void
    {
        $user = static::findUserByUsername($username);
        self::assertNotNull($user);
        self::assertSame($emailAddress, $user->getEmailAddress());
        self::assertNull($user->getNewEmailAddress());
    }

    protected static function assertRefusedEmailLinkLogged(string $username): void
    {
        static::assertLogged(\sprintf('a refused email link for %s', $username), static fn (LogRecord $record): bool => Level::Error === $record->level
            && ($record->context['user'] ?? null) === $username
            && ($record->context['exception'] ?? null) instanceof UnparseableRequestHeaderException);
    }

    protected static function assertSentEmailLinkStartsWith(string $prefix): void
    {
        self::assertStringStartsWith($prefix, static::sentEmailLink());
    }

    protected static function assertEmailOfTypeSent(string $type, string $emailAddress = 'user@example.com'): TemplatedEmail
    {
        $email = static::assertOneTemplatedEmailSentTo($emailAddress);
        $context = $email->getContext();
        $headers = $email->getHeaders();
        $subject = $headers->get('subject')?->getBodyAsString();
        $messageId = (string) $headers->get('x-message-id')?->getBodyAsString();
        $token = '[^\/]+';

        [$expectedSubject, $prefix, $link] = match ($type) {
            'verify_email' => ['Please verify your email', VerifyEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www.website.com\/verify-email\/my_username\/' . $token . '$/i'],
            'username_changed_notification' => ['Your username has been updated', UsernameChangedEmailFactory::MESSAGE_ID_PREFIX, null],
            'enabled_notification' => ['Your account has been enabled', UserEnabledEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www\.website\.com\/login$/i'],
            'custom_change_email_confirmation' => ['Please confirm your new email address', ChangeEmailConfirmationEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www\.website\.com\/another-path\/new_user\/new%40example.com\/' . $token . '$/i'],
            'change_email_confirmation' => ['Please confirm your new email address', ChangeEmailConfirmationEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www\.website\.com\/confirm-new-email\/' . preg_quote($context['user']->getUsername(), '/') . '\/new%40example.com\/' . $token . '$/i'],
            'change_password_notification', 'password_changed' => ['Your password has been changed', PasswordChangedEmailFactory::MESSAGE_ID_PREFIX, null],
            'user_welcome' => ['Welcome to New Website', WelcomeEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www.website.com\/verify-email\/new_user\/' . $token . '$/i'],
            'password_reset' => ['Your password reset request', PasswordResetEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www.website.com\/reset-password\/my_username\/' . $token . '$/i'],
            'custom_password_reset' => ['Your password reset request', PasswordResetEmailFactory::MESSAGE_ID_PREFIX, '/^http:\/\/www.website.com\/another-path\/my_username\/' . $token . '$/i'],
        };

        self::assertSame($expectedSubject, $subject);
        self::assertStringStartsWith($prefix, $messageId);
        if (\in_array($type, ['change_email_confirmation', 'custom_change_email_confirmation'], true)) {
            self::assertIsString($context['user']->getNewEmailConfirmationToken());
        }
        if (null !== $link) {
            self::assertMatchesRegularExpression($link, $context['enabled_notification' === $type ? 'login_url' : 'redirect_url']);
        }

        return $email;
    }

    protected static function createRefreshToken(User $user, string $expiresAt = '+10 seconds'): void
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $stored = $manager->find(User::class, $user->getId());
        foreach ($manager->getRepository(RefreshToken::class)->findBy(['user' => $stored]) as $token) {
            $manager->remove($token);
        }
        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($stored)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt(new \DateTimeImmutable($expiresAt));
        $manager->persist($refreshToken);
        $manager->flush();
        static::rebootKernel();
    }

    /**
     * @return list<RefreshToken>
     */
    protected static function refreshTokensOf(?User $user = null): array
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $repository = $manager->getRepository(RefreshToken::class);

        return array_values(null === $user ? $repository->findAll() : $repository->findBy(['user' => $user->getId()]));
    }

    protected static function assertRefreshTokenCount(int $count): void
    {
        $tokens = static::refreshTokensOf();
        self::assertCount($count, $tokens);
        $live = \count(array_filter($tokens, static fn (RefreshToken $token): bool => !$token->isExpired()));
        self::assertLessThanOrEqual(1, $live, \sprintf('There should only be 1 token that is not expired. There are %d', $live));
    }

    protected static function assertRefreshTokenExpired(User $user, bool $expired = true): void
    {
        $tokens = static::refreshTokensOf($user);
        self::assertNotEmpty($tokens, 'The user has no refresh token.');
        self::assertSame($expired, $tokens[0]->isExpired());
    }

    protected static function assertAllRefreshTokensExpired(User $user): void
    {
        foreach (static::refreshTokensOf($user) as $token) {
            self::assertTrue($token->isExpired(), 'A refresh token is not expired.');
        }
    }

    protected static function setExpiredJwtCookie(Client $client, User $user): void
    {
        $token = static::getContainer()->get(JWTEncoderInterface::class)->encode([
            'exp' => (new \DateTime('-1 second'))->getTimestamp(),
            'username' => $user->getUsername(),
        ]);
        static::rebootKernel();
        $client->getKernelBrowser()->getCookieJar()->set(new Cookie('api_components', $token));
        $client->getKernelBrowser()->setServerParameter('HTTP_AUTHORIZATION', '');
    }

    protected static function setInvalidBearerToken(Client $client): void
    {
        $encode = static fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $token = implode('.', [
            $encode(['typ' => 'JWT', 'alg' => 'RS256']),
            $encode(['iat' => 1605117143, 'exp' => 1605120743, 'roles' => ['ROLE_ADMIN', 'ROLE_USER'], 'username' => 'admin']),
            rtrim(strtr(base64_encode(random_bytes(256)), '+/', '-_'), '='),
        ]);
        $client->getKernelBrowser()->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
    }

    protected static function assertJwtCookieCleared(): void
    {
        static::assertResponseCookieExpiresWithin('api_components', 2);
        self::assertSame('x.x.x', static::responseCookie('api_components')->getValue());
    }
}
