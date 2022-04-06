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

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\MinkContext;
use PHPUnit\Framework\Assert;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpKernel\Profiler\Profile as HttpProfile;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mime\Header\Headers;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ProfilerContext implements Context
{
    private ?AbstractBrowser $client;

    /**
     * @BeforeScenario
     */
    public function getContexts(BeforeScenarioScope $scope)
    {
        /** @var MinkContext $mink */
        $mink = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->client = $mink->getSession()->getDriver()->getClient();
    }

    /**
     * @Then I should not receive any emails
     */
    public function iShouldNotReceiveAnyEmails()
    {
        /** @var MessageDataCollector $collector */
        $collector = $this->getProfile()->getCollector('mailer');
        $messages = $collector->getEvents()->getMessages();
        Assert::assertCount(0, $messages);
    }

    /**
     * @Then /^I should get a(?:n|) "(.+)" email sent(?:| to the email address "(.+)")$/i
     */
    public function iShouldGetAnEmail(string $emailType, string $emailAddress = 'user@example.com')
    {
        /** @var MessageDataCollector $collector */
        $collector = $this->getProfile()->getCollector('mailer');
        $messages = $collector->getEvents()->getMessages();
        Assert::assertCount(1, $messages);
        Assert::assertInstanceOf(TemplatedEmail::class, $email = $messages[0]);

        /** @var TemplatedEmail $email */
        $context = $email->getContext();
        Assert::assertEquals('New Website', $context['website_name']);
        Assert::assertInstanceOf(User::class, $context['user']);

        /** @var Headers $headers */
        $headers = $email->getHeaders();

        Assert::assertEquals($emailAddress, $headers->get('to')->getBodyAsString());
        Assert::assertEquals('test@website.com', $headers->get('from')->getBodyAsString());

        switch ($emailType) {
            case 'verify_email':
                $this->verifyEmail($context, $headers);
                break;
            case 'username_changed_notification':
                $this->usernameChangedNotification($headers);
                break;
            case 'enabled_notification':
                $this->validateEnabledNotification($headers);
                break;
            case 'custom_change_email_confirmation':
                $this->validateChangeEmailVerification($context, $headers, true);
                break;
            case 'change_email_confirmation':
                $this->validateChangeEmailVerification($context, $headers);
                break;
            case 'change_password_notification':
                $this->validateChangePasswordNotification($headers);
                break;
            case 'user_welcome':
                $this->validateUserWelcomeEmail($context, $headers);
                break;
            case 'password_reset':
                $this->validatePasswordReset($context, $headers);
                break;
            case 'custom_password_reset':
                $this->validatePasswordReset($context, $headers, true);
                break;
            case 'password_changed':
                $this->validatePasswordChanged($headers);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('The email type %s is not configured to test', $emailType));
        }
    }

    private function verifyEmail(array $context, Headers $headers): void
    {
        Assert::assertEquals('Please verify your email', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(VerifyEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertRegExp('/^http:\/\/www.website.com\/verify-email\/my_username\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function usernameChangedNotification(Headers $headers): void
    {
        Assert::assertEquals('Your username has been updated', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(UsernameChangedEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function validateEnabledNotification(Headers $headers): void
    {
        Assert::assertEquals('Your account has been enabled', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(UserEnabledEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function validateChangeEmailVerification(array $context, Headers $headers, bool $customPath = false): void
    {
        $pathInsert = $customPath ? 'another-path' : 'confirm-new-email';
        Assert::assertEquals('Please confirm your new email address', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(ChangeEmailConfirmationEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertIsString($context['user']->getNewEmailConfirmationToken());
        Assert::assertRegExp('/^http:\/\/www.website.com\/' . $pathInsert . '\/user%40example.com\/new%40example.com\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function validateChangePasswordNotification(Headers $headers): void
    {
        Assert::assertEquals('Your password has been changed', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordChangedEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function validateUserWelcomeEmail(array $context, Headers $headers): void
    {
        Assert::assertEquals('Welcome to New Website', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(WelcomeEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertRegExp('/^http:\/\/www.website.com\/verify-email\/user%40example.com\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function validatePasswordReset(array $context, Headers $headers, bool $customPath = false): void
    {
        $pathInsert = $customPath ? 'another-path' : 'reset-password';
        Assert::assertEquals('Your password has been reset', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordResetEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertRegExp('/^http:\/\/www.website.com\/' . $pathInsert . '\/my_username\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function validatePasswordChanged(Headers $headers): void
    {
        Assert::assertEquals('Your password has been changed', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordChangedEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function getProfile(): HttpProfile
    {
        $profile = $this->client->getProfile();
        if (!$profile) {
            throw new \Exception('No client profile exists');
        }

        return $profile;
    }
}
