<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\User;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Helper\User\UserWriteNotifier;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class UserWriteNotifierTest extends TestCase
{
    private const array SEND_METHODS = [
        'sendWelcomeEmail',
        'sendUserEnabledEmail',
        'sendUsernameChangedEmail',
        'sendPasswordChangedEmail',
        'sendEmailVerifyEmailAfterWrite',
        'sendChangeEmailConfirmationEmailAfterWrite',
    ];

    public function test_a_new_user_gets_only_the_welcome_email(): void
    {
        $user = $this->user();

        $this->notify($user, null, ['sendWelcomeEmail']);
    }

    public function test_an_unchanged_user_gets_no_email(): void
    {
        $previous = $this->user();

        $this->notify(clone $previous, $previous, []);
    }

    /**
     * @return iterable<string, array{\Closure(User): void, list<string>}>
     */
    public static function changes(): iterable
    {
        yield 'enabled' => [static function (User $user): void {
            $user->setEnabled(true);
        }, ['sendUserEnabledEmail']];
        yield 'username changed' => [static function (User $user): void {
            $user->setUsername('renamed');
        }, ['sendUsernameChangedEmail']];
        yield 'password changed' => [static function (User $user): void {
            $user->setPassword('new-hash');
        }, ['sendPasswordChangedEmail']];
        yield 'new email verification token' => [static function (User $user): void {
            $user->setEmailAddressVerifyToken('hashed');
            $user->plainEmailAddressVerifyToken = 'plain';
        }, ['sendEmailVerifyEmailAfterWrite']];
        yield 'new email verification token with no plain token to send' => [static function (User $user): void {
            $user->setEmailAddressVerifyToken('hashed');
        }, []];
        yield 'new email change confirmation token' => [static function (User $user): void {
            $user->setNewEmailConfirmationToken('hashed');
        }, ['sendChangeEmailConfirmationEmailAfterWrite']];
        yield 'enabled and renamed' => [static function (User $user): void {
            $user->setEnabled(true);
            $user->setUsername('renamed');
        }, ['sendUserEnabledEmail', 'sendUsernameChangedEmail']];
    }

    /**
     * @param \Closure(User): void $change
     * @param list<string>         $expected
     */
    #[DataProvider('changes')]
    public function test_an_updated_user_gets_an_email_for_each_change(\Closure $change, array $expected): void
    {
        $previous = $this->user();
        $user = clone $previous;
        $change($user);

        $this->notify($user, $previous, $expected);
    }

    public function test_an_unchanged_token_is_not_sent_again(): void
    {
        $previous = $this->user();
        $previous->setEmailAddressVerifyToken('hashed');
        $previous->setNewEmailConfirmationToken('hashed');
        $user = clone $previous;
        $user->plainEmailAddressVerifyToken = 'plain';

        $this->notify($user, $previous, []);
    }

    public function test_a_disabled_user_is_not_sent_the_enabled_email(): void
    {
        $previous = $this->user();
        $previous->setEnabled(true);
        $user = clone $previous;
        $user->setEnabled(false);

        $this->notify($user, $previous, []);
    }

    private function user(): User
    {
        $user = new User('daniel', 'daniel@example.com', false, ['ROLE_USER'], 'hash', false);
        $user->setNewEmailConfirmationToken(null);

        return $user;
    }

    /**
     * @param list<string> $expected
     */
    private function notify(User $user, ?User $previous, array $expected): void
    {
        $mailer = $this->createMock(UserMailer::class);
        foreach (self::SEND_METHODS as $method) {
            $mailer->expects(\in_array($method, $expected, true) ? self::once() : self::never())->method($method)->with($user);
        }

        (new UserWriteNotifier($mailer))->notify($user, $previous);
    }
}
