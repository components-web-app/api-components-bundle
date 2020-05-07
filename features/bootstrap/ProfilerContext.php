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
use Silverback\ApiComponentsBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\BrowserKit\AbstractBrowser;
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
        $collector = $this->client->getProfile()->getCollector('mailer');
        $messages = $collector->getEvents()->getMessages();
        Assert::assertCount(0, $messages);
    }

    /**
     * @Then /^I should get a(?:n|) "(.+)" email sent(?:| to the email address "(.+)")$/i
     */
    public function iShouldGetAnEmail(string $emailType, string $emailAddress = 'user@example.com')
    {
        /** @var MessageDataCollector $collector */
        $collector = $this->client->getProfile()->getCollector('mailer');
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
            case 'change_email_verification':
                $this->validateChangeEmailVerification($context, $headers);
                break;
            case 'change_password_notification':
                $this->validateChangePasswordNotification($headers);
                break;
            case 'user_welcome':
                $this->validateUserWelcomeEmail($headers);
                break;
            case 'password_reset':
                $this->validatePasswordReset($context, $headers);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('The email type %s is not configured to test', $emailType));
        }
    }

    private function validateChangeEmailVerification(array $context, Headers $headers): void
    {
        Assert::assertEquals('Please verify your email', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(ChangeEmailVerificationEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertIsString($context['user']->getNewEmailVerificationToken());
        Assert::assertRegExp('/^http:\/\/www.website.com\/verify-new-email\/user%40example.com\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function validateChangePasswordNotification(Headers $headers): void
    {
        Assert::assertEquals('Your password has been changed', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordChangedEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function validateUserWelcomeEmail(Headers $headers): void
    {
        Assert::assertEquals('Welcome to New Website', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(WelcomeEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function validatePasswordReset(array $context, Headers $headers): void
    {
        Assert::assertEquals('Your password has been reset', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordResetEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertRegExp('/^http:\/\/www.website.com\/reset-password\/my_username\/([a-z0-9]+)$/i', $context['redirect_url']);
    }
}
