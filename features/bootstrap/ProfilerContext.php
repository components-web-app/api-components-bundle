<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Assert;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Tests\Functional\MockClientCallback;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\EventSubscriber\TemplatedEmailMessageEventSubscriber;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\SwitchableMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\MercureBundle\DataCollector\MercureDataCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile as HttpProfile;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ProfilerContext implements Context
{
    private const PURGE_HEADER_NAMES = ['xkey', 'surrogate-key'];
    private const EMAIL_LINK_DEFAULT_ORIGIN_ENV = 'EMAIL_LINK_DEFAULT_ORIGIN';

    private ?HttpClientDataCollector $outOfRequestHttpClientCollector = null;
    private ?RestContext $restContext;
    private ?MinkContext $minkContext;
    private ?JsonContext $jsonContext;

    public function __construct(private readonly ContainerInterface $driverContainer)
    {
    }

    /**
     * @BeforeScenario
     */
    public function getContexts(BeforeScenarioScope $scope)
    {
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->jsonContext = $scope->getEnvironment()->getContext(JsonContext::class);
        $this->outOfRequestHttpClientCollector = null;
    }

    public function useOutOfRequestHttpClientCollector(HttpClientDataCollector $collector): void
    {
        $this->outOfRequestHttpClientCollector = $collector;
    }

    private function getHttpClientCollector(): HttpClientDataCollector
    {
        if ($this->outOfRequestHttpClientCollector) {
            return $this->outOfRequestHttpClientCollector;
        }

        /* @var HttpClientDataCollector $collector */
        return $this->getProfile()->getCollector('http_client');
    }

    /**
     * @BeforeScenario
     *
     * @AfterScenario
     */
    public function resetMercureHub(): void
    {
        HubStub::setUnreachable(false);
    }

    /**
     * @BeforeScenario
     *
     * @AfterScenario
     */
    public function resetEmailLinkDefaultOrigin(): void
    {
        unset($_SERVER[self::EMAIL_LINK_DEFAULT_ORIGIN_ENV], $_ENV[self::EMAIL_LINK_DEFAULT_ORIGIN_ENV]);
    }

    /**
     * @Given links in user emails default to the origin :origin
     */
    public function linksInUserEmailsDefaultToTheOrigin(string $origin): void
    {
        $_SERVER[self::EMAIL_LINK_DEFAULT_ORIGIN_ENV] = $_ENV[self::EMAIL_LINK_DEFAULT_ORIGIN_ENV] = $origin;
    }

    /**
     * @BeforeScenario
     *
     * @AfterScenario
     */
    public function resetDatabaseReachability(): void
    {
        UnreachableDatabaseMiddleware::setUnreachable(false);
    }

    /**
     * @Given the database is unreachable
     */
    public function theDatabaseIsUnreachable(): void
    {
        UnreachableDatabaseMiddleware::setUnreachable(true);
    }

    /**
     * @Then the unreachable database should have been logged
     */
    public function theUnreachableDatabaseShouldHaveBeenLogged(): void
    {
        /** @var TestHandler $handler */
        $handler = $this->driverContainer->get('app.monolog.test_handler');
        foreach ($handler->getRecords() as $record) {
            $exception = $record->context['exception'] ?? null;
            if (Level::Warning === $record->level && $exception instanceof \Throwable && $exception->getPrevious() instanceof UnreachableDatabaseException) {
                return;
            }
        }

        throw new ExpectationException('No unreachable database was logged.', $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then a refused email link for the user :username should have been logged
     */
    public function aRefusedEmailLinkForTheUserShouldHaveBeenLogged(string $username): void
    {
        /** @var TestHandler $handler */
        $handler = $this->driverContainer->get('app.monolog.test_handler');
        foreach ($handler->getRecords() as $record) {
            if (Level::Error === $record->level && ($record->context['user'] ?? null) === $username && ($record->context['exception'] ?? null) instanceof UnparseableRequestHeaderException) {
                return;
            }
        }

        throw new ExpectationException(\sprintf('No refused email link was logged for the user %s.', $username), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @BeforeScenario
     *
     * @AfterScenario
     */
    public function resetMailer(): void
    {
        SwitchableMailer::setUnreachable(false);
    }

    /**
     * @Given the mailer is unreachable
     */
    public function theMailerIsUnreachable(): void
    {
        SwitchableMailer::setUnreachable(true);
    }

    /**
     * @Given the mailer is reachable again
     */
    public function theMailerIsReachableAgain(): void
    {
        SwitchableMailer::setUnreachable(false);
    }

    /**
     * @Given the Mercure hub is unreachable
     */
    public function theMercureHubIsUnreachable(): void
    {
        HubStub::setUnreachable(true);
    }

    /**
     * @BeforeScenario
     *
     * @AfterScenario
     */
    public function resetHttpCache(): void
    {
        MockClientCallback::setCacheUnreachable(false);
    }

    /**
     * @Given the HTTP cache is unreachable
     */
    public function theHttpCacheIsUnreachable(): void
    {
        MockClientCallback::setCacheUnreachable(true);
    }

    /**
     * @return LogRecord[]
     */
    private function getCachePurgeFailureRecords(): array
    {
        /** @var TestHandler $handler */
        $handler = $this->driverContainer->get('app.monolog.test_handler');

        return array_values(array_filter(
            $handler->getRecords(),
            static fn (LogRecord $record): bool => Level::Error === $record->level && \is_array($record->context['tags'] ?? null) && ($record->context['exception'] ?? null) instanceof HttpClientExceptionInterface
        ));
    }

    /**
     * @Then the cache purge failure for the resource :name should have been logged
     */
    public function theCachePurgeFailureForTheResourceShouldHaveBeenLogged(string $name): void
    {
        $iri = $this->restContext->resources[$name];
        foreach ($this->getCachePurgeFailureRecords() as $record) {
            if (\in_array($iri, $record->context['tags'], true)) {
                return;
            }
        }

        throw new ExpectationException(\sprintf('No error was logged for the failed cache purge of %s.', $iri), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then no cache purge failure should have been logged
     */
    public function noCachePurgeFailureShouldHaveBeenLogged(): void
    {
        $records = $this->getCachePurgeFailureRecords();
        if ([] !== $records) {
            throw new ExpectationException(\sprintf('%d cache purge failures were logged.', \count($records)), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then a Mercure update should have been published for the resource :name
     */
    public function aMercureUpdateShouldHaveBeenPublishedForTheResource(string $name): void
    {
        $iri = $this->restContext->resources[$name];
        foreach ($this->getMercureMessageObjects() as $update) {
            foreach ($update->getTopics() as $topic) {
                if (str_ends_with($topic, $iri)) {
                    return;
                }
            }
        }

        throw new ExpectationException(\sprintf('No Mercure update was published for %s.', $iri), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @return LogRecord[]
     */
    private function getMercurePublishFailureRecords(): array
    {
        /** @var TestHandler $handler */
        $handler = $this->driverContainer->get('app.monolog.test_handler');

        return array_values(array_filter(
            $handler->getRecords(),
            static fn (LogRecord $record): bool => ($record->context['exception'] ?? null) instanceof MercureRuntimeException
        ));
    }

    /**
     * @Then a missing source image for the imagine filter :filter should have been logged
     */
    public function aMissingSourceImageForTheImagineFilterShouldHaveBeenLogged(string $filter): void
    {
        /** @var TestHandler $handler */
        $handler = $this->driverContainer->get('app.monolog.test_handler');
        foreach ($handler->getRecords() as $record) {
            if (Level::Warning === $record->level && $filter === ($record->context['filter'] ?? null) && ($record->context['exception'] ?? null) instanceof NotLoadableException) {
                return;
            }
        }

        throw new ExpectationException(\sprintf('No missing source image was logged for the imagine filter "%s".', $filter), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then the Mercure publish failure for the resource :name should have been logged
     */
    public function theMercurePublishFailureForTheResourceShouldHaveBeenLogged(string $name): void
    {
        $iri = $this->restContext->resources[$name];
        foreach ($this->getMercurePublishFailureRecords() as $record) {
            $topics = $record->context['topics'] ?? [];
            if (Level::Error === $record->level && \is_string($record->context['resource'] ?? null) && str_ends_with($record->context['resource'], $iri) && \in_array($record->context['resource'], $topics, true)) {
                return;
            }
        }

        throw new ExpectationException(\sprintf('No error was logged for the failed Mercure update of %s.', $iri), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then no Mercure publish failure should have been logged
     */
    public function noMercurePublishFailureShouldHaveBeenLogged(): void
    {
        $records = $this->getMercurePublishFailureRecords();
        if ([] !== $records) {
            throw new ExpectationException(\sprintf('%d Mercure publish failures were logged.', \count($records)), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @return Update[]
     */
    private function getMercureMessageObjects(): array
    {
        $objects = [];
        /** @var MercureDataCollector $collector */
        $collector = $this->getProfile()->getCollector('mercure');
        $hubs = $collector->getHubs();
        foreach ($hubs['default']['messages'] as $message) {
            $objects[] = $message['object'];
        }

        return $objects;
    }

    /**
     * @Then there should be :count mercure messages
     *
     * @return Update[]
     */
    public function thereShouldBeAPublishedMercureUpdatePublished(?int $count = null)
    {
        $messageObjects = $this->getMercureMessageObjects();
        if (null !== $count && \count($messageObjects) !== $count) {
            throw new ExpectationException(\sprintf('%d updates were published but %d were expected', \count($messageObjects), $count), $this->minkContext->getSession()->getDriver());
        }

        return $messageObjects;
    }

    /**
     * @Then the Mercure message should contain timestamped fields
     */
    public function theMercureMessageShouldContainTimestampedFields()
    {
        $messageObjects = $this->thereShouldBeAPublishedMercureUpdatePublished(1);
        $update = $messageObjects[0];
        $messageData = $update->getData();
        $messageArray = $this->jsonContext->getJsonAsArray($messageData);
        Assert::assertArrayHasKey('createdAt', $messageArray);
        Assert::assertArrayHasKey('modifiedAt', $messageArray);
    }

    private function getMercureComponentGroupMessage()
    {
        $messageObjects = $this->thereShouldBeAPublishedMercureUpdatePublished();
        foreach ($messageObjects as $messageObject) {
            $messageData = $messageObject->getData();
            $messageAsArray = $this->jsonContext->getJsonAsArray($messageData);
            if ('/contexts/ComponentGroup' === $messageAsArray['@context']) {
                return $messageAsArray;
            }
        }
        throw new ExpectationException(\sprintf('%d updates were published but no ComponentGroup was found', \count($messageObjects)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then the Mercure message for component group should contain timestamped fields
     */
    public function theMercureMessageForComponentGroupShouldContainTimestampedFields()
    {
        $messageArray = $this->getMercureComponentGroupMessage();
        Assert::assertArrayHasKey('createdAt', $messageArray);
        Assert::assertArrayHasKey('modifiedAt', $messageArray);
    }

    /**
     * @Then the Mercure message for component group should contain :count component position
     */
    public function theMercureMessageForTheComponentGroupShouldContainCompontnPosition(int $count)
    {
        $messageArray = $this->getMercureComponentGroupMessage();
        Assert::assertCount($count, $messageArray['componentPositions']);
    }

    /**
     * @Then Mercure updates should have been published for exactly the ComponentPositions :names
     */
    public function mercureUpdatesShouldHaveBeenPublishedForExactlyTheComponentPositions(string $names): void
    {
        $expected = [];
        foreach (array_map('trim', explode(',', $names)) as $name) {
            $expected[] = $this->restContext->resources[$name];
        }
        $published = [];
        foreach ($this->getMercureMessageObjects() as $messageObject) {
            foreach ($messageObject->getTopics() as $topic) {
                $path = parse_url($topic, \PHP_URL_PATH) ?: $topic;
                if (str_contains($path, '/component_positions/')) {
                    $published[] = $path;
                }
            }
        }
        $published = array_values(array_unique($published));
        sort($expected);
        sort($published);
        if ($expected !== $published) {
            throw new ExpectationException(\sprintf('Expected Mercure updates for the ComponentPositions [%s] but they were published for [%s]', implode(', ', $expected), implode(', ', $published)), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then there should be :count mercure messages for draft resources
     */
    public function thereShouldMercureMessagesForDraftResources(int $count)
    {
        $messageObjects = $this->getMercureMessageObjects();
        $draftCount = 0;
        foreach ($messageObjects as $messageObject) {
            $iri = $messageObject->getTopics()[0];
            if (str_ends_with($iri, '?draft=1')) {
                if (!$messageObject->isPrivate()) {
                    throw new ExpectationException('Draft resource messages must be private', $this->minkContext->getSession()->getDriver());
                }
                ++$draftCount;
            }
        }
        if ($draftCount !== $count) {
            throw new ExpectationException(\sprintf('%d draft updates were published but %d were expected', $draftCount, $count), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @return list<string>
     */
    private function collectPurgedTags(): array
    {
        $collector = $this->getHttpClientCollector();
        $purged = [];
        foreach ($collector->getClients() as $clientInfo) {
            foreach ($clientInfo['traces'] as $trace) {
                /** @var Data $data */
                $data = $trace['options']->getValue()['normalized_headers'];
                $normalizedHeaders = $data->getValue();
                foreach (self::PURGE_HEADER_NAMES as $headerName) {
                    if (!isset($normalizedHeaders[$headerName])) {
                        continue;
                    }
                    foreach ($normalizedHeaders[$headerName]->getValue() as $header) {
                        $value = preg_replace('/^' . preg_quote($headerName, '/') . ':\s*/i', '', $header->getValue());
                        array_push($purged, ...preg_split('/[,\s]+/', trim($value), -1, \PREG_SPLIT_NO_EMPTY));
                    }
                }
            }
        }

        return $purged;
    }

    /**
     * @Then the resource :resource_name should be purged from the cache
     */
    public function theResourceShouldBePurgedFromTheCache(string $resourceName)
    {
        $expectedIri = $this->restContext->resources[$resourceName];
        $purged = $this->collectPurgedTags();
        if (\in_array($expectedIri, $purged, true)) {
            return;
        }
        throw new ExpectationException(\sprintf('The resource %s was not found in any purge headers sent. Tags that were purged were `%s`', $expectedIri, implode('`, `', $purged)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then the cache tag :tag should be purged
     */
    public function theCacheTagShouldBePurged(string $tag)
    {
        $purged = $this->collectPurgedTags();
        if (\in_array($tag, $purged, true)) {
            return;
        }
        throw new ExpectationException(\sprintf('The cache tag %s was not found in any purge headers sent. Tags that were purged were `%s`', $tag, implode('`, `', $purged)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then the cache tag :tag should not be purged
     */
    public function theCacheTagShouldNotBePurged(string $tag)
    {
        $purged = $this->collectPurgedTags();
        if (!\in_array($tag, $purged, true)) {
            return;
        }
        throw new ExpectationException(\sprintf('The cache tag %s should not have been purged. Tags that were purged were `%s`', $tag, implode('`, `', $purged)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then no cache purge request should have been sent
     */
    public function noCachePurgeRequestShouldHaveBeenSent(): void
    {
        $collector = $this->getHttpClientCollector();
        $sent = [];
        foreach ($collector->getClients() as $clientInfo) {
            foreach ($clientInfo['traces'] as $trace) {
                if ('PURGE' === $trace['method']) {
                    $sent[] = $trace['url'];
                }
            }
        }
        if ([] === $sent) {
            return;
        }
        throw new ExpectationException(\sprintf('No cache purge request should have been sent, but PURGE requests were sent to `%s`', implode('`, `', $sent)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then /^the cache tag "([^"]*)" should be purged (\d+) times?$/
     */
    public function theCacheTagShouldBePurgedTimes(string $tag, int $count)
    {
        $purged = $this->collectPurgedTags();
        $actual = \count(array_filter($purged, static fn (string $purgedTag): bool => $purgedTag === $tag));
        if ($actual === $count) {
            return;
        }
        throw new ExpectationException(\sprintf('The cache tag %s was purged %d time(s) but %d was expected. Tags that were purged were `%s`', $tag, $actual, $count, implode('`, `', $purged)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then :tag should be the only cache tag purged
     */
    public function theCacheTagShouldBeTheOnlyCacheTagPurged(string $tag)
    {
        $purged = $this->collectPurgedTags();
        if ([] !== $purged && [$tag] === array_values(array_unique($purged))) {
            return;
        }
        throw new ExpectationException(\sprintf('The cache tag %s should have been the only tag purged. Tags that were purged were `%s`', $tag, implode('`, `', $purged)), $this->minkContext->getSession()->getDriver());
    }

    /**
     * @Then the manifest cache tag for the resource :resource_name should be purged
     */
    public function theManifestCacheTagForTheResourceShouldBePurged(string $resourceName)
    {
        $this->theCacheTagShouldBePurged($this->manifestTagForResource($resourceName));
    }

    /**
     * @Then the manifest cache tag for the resource :resource_name should not be purged
     */
    public function theManifestCacheTagForTheResourceShouldNotBePurged(string $resourceName)
    {
        $this->theCacheTagShouldNotBePurged($this->manifestTagForResource($resourceName));
    }

    /**
     * @Then the manifest cache tag for the resource :resource_name should be in the response
     */
    public function theManifestCacheTagForTheResourceShouldBeInTheResponse(string $resourceName)
    {
        $this->theCacheTagShouldBeInTheResponse($this->manifestTagForResource($resourceName));
    }

    /**
     * @Then the cache tag for the resource :resource_name should be in the response
     */
    public function theCacheTagForTheResourceShouldBeInTheResponse(string $resourceName)
    {
        $this->theCacheTagShouldBeInTheResponse($this->restContext->resources[$resourceName]);
    }

    /**
     * @Then the cache tag for the resource :resource_name should not be in the response
     */
    public function theCacheTagForTheResourceShouldNotBeInTheResponse(string $resourceName)
    {
        $tag = $this->restContext->resources[$resourceName];
        $tags = $this->collectResponseTags();
        if (!\in_array($tag, $tags, true)) {
            return;
        }
        throw new ExpectationException(\sprintf('The cache tag %s should not have been in the response. Tags in the response were `%s`', $tag, implode('`, `', $tags)), $this->minkContext->getSession()->getDriver());
    }

    private function theCacheTagShouldBeInTheResponse(string $tag): void
    {
        $tags = $this->collectResponseTags();
        if (\in_array($tag, $tags, true)) {
            return;
        }
        throw new ExpectationException(\sprintf('The cache tag %s was not in the response. Tags in the response were `%s`', $tag, implode('`, `', $tags)), $this->minkContext->getSession()->getDriver());
    }

    private function manifestTagForResource(string $resourceName): string
    {
        return CwaTagCollector::MANIFEST_TAG_PREFIX . $this->restContext->resources[$resourceName];
    }

    /**
     * @return list<string>
     */
    private function collectResponseTags(): array
    {
        $headers = array_change_key_case($this->minkContext->getSession()->getResponseHeaders());
        $tags = [];
        foreach (self::PURGE_HEADER_NAMES as $headerName) {
            foreach ((array) ($headers[$headerName] ?? []) as $value) {
                array_push($tags, ...preg_split('/[,\s]+/', trim((string) $value), -1, \PREG_SPLIT_NO_EMPTY));
            }
        }

        return $tags;
    }

    /**
     * @Then I should not receive any emails
     */
    public function iShouldNotReceiveAnyEmails()
    {
        /** @var MessageDataCollector $collector */
        $collector = $this->getProfile()->getCollector('mailer');
        $messages = $collector->getEvents()->getMessages();
        if (0 !== \count($messages)) {
            throw new ExpectationException(\sprintf('Expected no emails but %d were sent', \count($messages)), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then /^I should get a(?:n|) "([^" ]*)" email sent(?:| to the email address "([^" ]*)")$/i
     */
    public function iShouldGetAnEmail(string $emailType, string $emailAddress = 'user@example.com')
    {
        /** @var TemplatedEmailMessageEventSubscriber $templatedEmailMessageEventSubscriber */
        $templatedEmailMessageEventSubscriber = $this->driverContainer->get(TemplatedEmailMessageEventSubscriber::class);

        /** @var TemplatedEmail[] $messages */
        $messages = iterator_to_array($templatedEmailMessageEventSubscriber->getMessages());

        $subjects = array_map(static function (TemplatedEmail $email) {
            return $email->getSubject();
        }, $messages);

        Assert::assertCount(1, $messages, \sprintf("%d messages were sent but only 1 was expected. Messages were sent with subjects '%s'", \count($messages), implode("', '", $subjects)));
        Assert::assertInstanceOf(TemplatedEmail::class, $email = $messages[0]);

        /** @var TemplatedEmail $email */
        $context = $email->getContext();
        Assert::assertArrayHasKey('website_name', $context);
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
                $this->validateEnabledNotification($context, $headers);
                break;
            case 'custom_change_email_confirmation':
                $this->validateChangeEmailVerification($context, $headers, true);
                break;
            case 'change_email_confirmation':
                $this->validateChangeEmailVerification($context, $headers, false, $context['user']->getUsername());
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
                throw new \InvalidArgumentException(\sprintf('The email type %s is not configured to test', $emailType));
        }
    }

    private function verifyEmail(array $context, Headers $headers): void
    {
        Assert::assertEquals('Please verify your email', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(VerifyEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertMatchesRegularExpression('/^http:\/\/www.website.com\/verify-email\/my_username\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function usernameChangedNotification(Headers $headers): void
    {
        Assert::assertEquals('Your username has been updated', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(UsernameChangedEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function validateEnabledNotification(array $context, Headers $headers): void
    {
        Assert::assertEquals('Your account has been enabled', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(UserEnabledEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertMatchesRegularExpression('/^http:\/\/www\.website\.com\/login$/i', $context['login_url']);
    }

    private function validateChangeEmailVerification(array $context, Headers $headers, bool $customPath = false, ?string $username = null): void
    {
        if (!$username) {
            $username = 'new_user';
        }
        $pathInsert = $customPath ? 'another-path' : 'confirm-new-email';
        Assert::assertEquals('Please confirm your new email address', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(ChangeEmailConfirmationEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertIsString($context['user']->getNewEmailConfirmationToken());
        Assert::assertMatchesRegularExpression('/^http:\/\/www\.website\.com\/' . $pathInsert . '\/' . $username . '\/new%40example.com\/([a-z0-9]+)$/i', $context['redirect_url']);
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
        Assert::assertMatchesRegularExpression('/^http:\/\/www.website.com\/verify-email\/new_user\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function validatePasswordReset(array $context, Headers $headers, bool $customPath = false): void
    {
        $pathInsert = $customPath ? 'another-path' : 'reset-password';
        Assert::assertEquals('Your password reset request', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordResetEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
        Assert::assertMatchesRegularExpression('/^http:\/\/www.website.com\/' . $pathInsert . '\/my_username\/([a-z0-9]+)$/i', $context['redirect_url']);
    }

    private function validatePasswordChanged(Headers $headers): void
    {
        Assert::assertEquals('Your password has been changed', $headers->get('subject')->getBodyAsString());
        Assert::assertStringStartsWith(PasswordChangedEmailFactory::MESSAGE_ID_PREFIX, $headers->get('x-message-id')->getBodyAsString());
    }

    private function getProfile(): HttpProfile
    {
        $profile = $this->minkContext->getSession()->getDriver()->getClient()->getProfile();
        if (!$profile) {
            throw new \Exception('No client profile exists');
        }

        return $profile;
    }

    /**
     * @Then the link in the sent email should start with :prefix
     */
    public function theLinkInTheSentEmailShouldStartWith(string $prefix): void
    {
        $messages = iterator_to_array($this->driverContainer->get(TemplatedEmailMessageEventSubscriber::class)->getMessages());
        if (1 !== \count($messages)) {
            throw new \RuntimeException(\sprintf('Expected 1 email, got %d', \count($messages)));
        }
        $context = $messages[0]->getContext();
        $link = $context['redirect_url'] ?? $context['login_url'] ?? null;
        if (!\is_string($link) || !str_starts_with($link, $prefix)) {
            throw new \RuntimeException(\sprintf('Expected the email link to start with "%s", got "%s"', $prefix, var_export($link, true)));
        }
    }
}
