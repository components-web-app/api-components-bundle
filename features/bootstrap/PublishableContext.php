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

namespace Silverback\ApiComponentBundle\Features\Bootstrap;

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behatch\Context\JsonContext as BehatchJsonContext;
use Behatch\Context\RestContext as BehatchRestContext;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Assert;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\PublishableComponent;

/**
 * @author Pierre Rebeilleau <pierre@les-tilleuls.coop>
 */
final class PublishableContext implements Context
{
    private ?BehatchRestContext $behatchRestContext;
    private ?BehatchJsonContext $behatchJsonContext;
    private ?JsonContext $jsonContext;
    private ObjectManager $manager;
    private IriConverterInterface $iriConverter;
    private array $resources = [];
    private array $publishedResourcesWithoutDrafts = [];
    private ?RestContext $restContext;
    private ?MinkContext $minkContext;

    public function __construct(ManagerRegistry $doctrine, IriConverterInterface $iriConverter)
    {
        $this->manager = $doctrine->getManager();
        $this->iriConverter = $iriConverter;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->behatchJsonContext = $scope->getEnvironment()->getContext(BehatchJsonContext::class);
        $this->behatchRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->jsonContext = $scope->getEnvironment()->getContext(JsonContext::class);
        $this->publishedResourcesWithoutDrafts = [];
    }

    /**
     * @Given there are :number draft and published resources available
     */
    public function givenThereAreDraftAndPublishedResourcesAvailable($number): void
    {
        for ($i = 0; $i < (int) $number; ++$i) {
            $publishedNow = $this->createPublishableComponent(new \DateTime());
            $draftUntilSoon = $this->thereIsAPublishableResource((new \DateTime())->modify('+10 seconds')->format('Y-m-d H:i:s'), false);
            $draftUntilSoon->setPublishedResource($publishedNow);

            $this->thereIsAPublicResourceWithADraftResourceAvailable();

            $publishedNoDraft = $this->createPublishableComponent((new \DateTime())->modify('-1 year'));
            $this->publishedResourcesWithoutDrafts[] = $publishedNoDraft;

            $draft = $this->thereIsAPublishableResource(null, false);
        }
        $this->manager->flush();
    }

    /**
     * @Given /^there is a published resource with a draft(?: set to publish at "(.*)"|)$/
     */
    public function thereIsAPublicResourceWithADraftResourceAvailable(?string $publishDate = null): void
    {
        $publishAt = $publishDate ? (new \DateTime($publishDate))->format('Y-m-d H:i:s') : null;
        $publishedRecently = $this->createPublishableComponent((new \DateTime())->modify('-10 seconds'));
        $draft = $this->thereIsAPublishableResource($publishAt, false);
        $draft->setPublishedResource($publishedRecently);
        $this->manager->flush();
    }

    /**
     * @Given /^there is a publishable resource(?: set to publish at "(.*)"|)$/
     */
    public function thereIsAPublishableResource(?string $publishDate = null, bool $flush = true): PublishableComponent
    {
        $object = $this->createPublishableComponent($publishDate ? new \DateTime($publishDate) : null);
        $flush && $this->manager->flush();

        return $object;
    }

    /**
     * @When I create a resource
     */
    public function iCreateAResource(): void
    {
        $this->behatchRestContext->iSendARequestTo('POST', '/publishable_components', new PyStringNode(
            ['{
                "name": "John Doe"
            }'],
            1
        ), );
    }

    /**
     * @Then the response should include the draft resources instead of the published ones
     */
    public function theResponseShouldIncludeTheDraftResourcesInsteadOfThePublishedOnes(): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        $items = $response['hydra:member'];
        $draftResources = array_filter($this->resources, static function (PublishableComponent $component) {
            return 'is_draft' === $component->reference;
        });

        $expectedTotal = \count($draftResources) + \count($this->publishedResourcesWithoutDrafts);

        if ($expectedTotal !== ($receivedTotal = \count($items))) {
            throw new ExpectationException(sprintf('Expected %d resources but received %d', $expectedTotal, $receivedTotal), $this->minkContext->getSession()->getDriver());
        }

        $expectedPublishedResourceIds = $this->getResourceIds($this->publishedResourcesWithoutDrafts);

        foreach ($items as $item) {
            if ('is_draft' !== $item['reference'] && !\in_array($item['@id'], $expectedPublishedResourceIds, true)) {
                throw new ExpectationException('Received an unexpected item in the response: ' . json_encode($item, JSON_THROW_ON_ERROR, 512), $this->minkContext->getSession()->getDriver());
            }
        }
    }

    /**
     * @Then the response should include the published resources only
     */
    public function theResponseShouldIncludeThePublishedResourcesOnly(): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        $items = $response['hydra:member'];

        $publishedResources = array_filter($this->resources, static function (PublishableComponent $component) {
            return 'is_published' === $component->reference;
        });

        $expectedTotal = \count($publishedResources);

        Assert::assertEquals($expectedTotal, $receivedTotal = \count($items), sprintf('Expected %d resources but received %d', $expectedTotal, $receivedTotal));

        foreach ($items as $item) {
            Assert::assertEquals('is_published', $item['reference'], 'Received an unexpected item in the response: ' . json_encode($item, JSON_THROW_ON_ERROR, 512));
        }
    }

    private function createPublishableComponent(?\DateTime $publishedAt): PublishableComponent
    {
        $isPublished = null !== $publishedAt && $publishedAt <= new \DateTime();
        $resource = new PublishableComponent();
        $resource->reference = $isPublished ? 'is_published' : 'is_draft';
        $resource->setPublishedAt($publishedAt);
        $this->manager->persist($resource);
        $this->resources[] = $resource;

        $componentKey = sprintf('publishable_%s', $isPublished ? 'published' : 'draft');
        $this->restContext->components[$componentKey] = $this->iriConverter->getIriFromItem($resource);

        return $resource;
    }

    private function getResourceIds(array $resources): array
    {
        return array_map(function (PublishableComponent $component) {
            return $this->iriConverter->getIriFromItem($component);
        }, $resources);
    }
}
