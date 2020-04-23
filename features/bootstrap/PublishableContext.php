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
    private RestContext $restContext;

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
        $this->publishedResourcesWithoutDrafts = [];
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
    }

    /**
     * @Transform now
     */
    public function transformNow(): \DateTime
    {
        return new \DateTime();
    }

    /**
     * @Transform null
     */
    public function transformNull()
    {
        return null;
    }

    /**
     * @Given there are draft and published resources available
     */
    public function givenThereAreDraftAndPublishedResourcesAvailable(): void
    {
        for ($i = 0; $i < 2; ++$i) {
            $publishedNow = $this->createPublishableComponent(new \DateTime());
            $draftUntilSoon = $this->thereIsAPublishableResource((new \DateTime())->modify('+10 seconds')->format('YYYY-mm-dd HH:ii:ss'));
            $draftUntilSoon->setPublishedResource($publishedNow);

            $this->thereIsAPublicResourceWithADraftResourceAvailable();

            // $publishedNoDraft
            $publishedNoDraft = $this->createPublishableComponent((new \DateTime())->modify('-1 year'));
            $this->publishedResourcesWithoutDrafts[] = $publishedNoDraft;

            // $draftNoPublished
            $this->thereIsAPublishableResource();
        }
    }

    /**
     * @Given there is a published resource with a draft(?: set to publish at "(.*)"|)
     */
    public function thereIsAPublicResourceWithADraftResourceAvailable(?string $publishDate = null): void
    {
        $publishAt = $publishDate ? new \DateTime($publishDate) : null;
        $publishedRecently = $this->createPublishableComponent((new \DateTime())->modify('-10 seconds'));
        $draft = $this->thereIsAPublishableResource($publishAt);
        $draft->setPublishedResource($publishedRecently);
    }

    /**
     * @Given there is a publishable resource(?: set to publish at "(.*)"|)
     */
    public function thereIsAPublishableResource(?string $publishDate = null): PublishableComponent
    {
        $publishAt = $publishDate ? new \DateTime($publishDate) : null;

        return $this->createPublishableComponent($publishAt);
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

        $draftResources = array_filter($this->resources, static function (PublishableComponent $component) {
            return 'is_draft' === $component['reference'];
        });

        $expectedTotal = \count($draftResources) + \count($this->publishedResourcesWithoutDrafts);
        if ($expectedTotal !== ($receivedTotal = \count($response))) {
            throw new \Exception(sprintf('Expected %d resources but received %d', $expectedTotal, $receivedTotal));
        }

        $expectedPublishedResourceIds = $this->getResourceIds($this->publishedResourcesWithoutDrafts);

        foreach ($response as $item) {
            if ('is_draft' !== $item['reference'] && !\in_array($item['id'], $expectedPublishedResourceIds, true)) {
                throw new \Exception('Received an unexpected item in the response: ' . json_encode($item, JSON_THROW_ON_ERROR, 512));
            }
        }
    }

    /**
     * @Then the response should include the published resources only
     */
    public function theResponseShouldIncludeThePublishedResourcesOnly(): void
    {
        $response = $this->jsonContext->getJsonAsArray();

        $publishedResources = array_filter($this->resources, static function (PublishableComponent $component) {
            return 'is_published' === $component['reference'];
        });

        $expectedTotal = \count($publishedResources);

        Assert::assertEquals($expectedTotal, $receivedTotal = \count($response), sprintf('Expected %d resources but received %d', $expectedTotal, $receivedTotal));

        foreach ($response as $item) {
            Assert::assertEquals('is_published', $item['reference'], 'Received an unexpected item in the response: ' . json_encode($item, JSON_THROW_ON_ERROR, 512));
        }
    }

    /**
     * @Then the response should include the key :arrayKey with the value :arrayValue
     */
    public function theResponseSHouldIncludeTheKeyWithValue($arrayKey, $arrayValue): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        Assert::assertArrayHasKey($arrayKey, $response);
        Assert::assertEquals($arrayValue, $response[$arrayKey]);
    }

    /**
     * @Then the response should be a published resource
     */
    public function theResponseShouldBeAPublishedResource(): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        $publishedAt = new \DateTime($response['publishedAt']);
        Assert::assertLessThanOrEqual(new \DateTime(), $publishedAt);
    }

    /**
     * @Then the response should be a draft resource
     */
    public function theResponseShouldBeADraftResource(): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        $publishedAt = new \DateTime($response['publishedAt']);
        if (null !== $publishedAt) {
            Assert::assertGreaterThan(new \DateTime(), $publishedAt);
        }
    }

    private function createPublishableComponent(?\DateTime $publishedAt): PublishableComponent
    {
        $isPublished = $publishedAt <= new \Date();
        $reference = $isPublished ? 'is_published' : 'is_draft';
        $resource = new PublishableComponent($reference);
        $resource->setPublishedAt($publishedAt);
        $this->manager->persist($resource);
        $this->resources[] = $resource;

        $componentKey = sprintf('publishable_%s', $isPublished ? 'published' : 'draft');
        $this->restContext->components[$componentKey] = $this->iriConverter->getIriFromItem($resource);

        return $resource;
    }

    private function getResourceIds(array $resources): array
    {
        return array_map(static function (PublishableComponent $component) {
            return $component->getId();
        }, $resources);
    }
}
