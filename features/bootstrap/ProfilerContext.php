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
     * @Then /^I should get a(?:n|) (.+) email sent$/i
     */
    public function iShouldGetAnEmail($emailType)
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
        Assert::assertEquals('user@example.com', $headers->get('to')->getBodyAsString());
        Assert::assertEquals('test@website.com', $headers->get('from')->getBodyAsString());
        Assert::assertEquals('Please verify your email', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(ChangeEmailVerificationEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }
}
