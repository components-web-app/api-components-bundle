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
    private array $resources = [];
    private array $publishedResourcesWithoutDrafts = [];

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->manager = $doctrine->getManager();
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->behatchJsonContext = $scope->getEnvironment()->getContext(BehatchJsonContext::class);
        $this->behatchRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
        $this->publishedResourcesWithoutDrafts = [];
    }

    /**
     * @Given there are draft and published resources available
     */
    public function givenThereAreDraftAndPublishedResourcesAvailable(): void
    {
        for ($i = 0; $i < 2; ++$i) {
            $publishedNow = $this->createPublishableComponent(new \DateTime(), 'is_published');
            $draftUntilSoon = $this->createPublishableComponent((new \DateTime())->modify('+10 seconds'), 'is_draft');
            $draftUntilSoon->setPublishedResource($publishedNow);

            $publishedRecently = $this->createPublishableComponent((new \DateTime())->modify('-10 seconds'), 'is_published');
            $alwaysDraft = $this->createPublishableComponent(null, 'is_draft');
            $alwaysDraft->setPublishedResource($publishedRecently);

            // $publishedNoDraft
            $publishedNoDraft = $this->createPublishableComponent((new \DateTime())->modify('-1 year'), 'is_published');
            $this->publishedResourcesWithoutDrafts[] = $publishedNoDraft;

            // $draftNoPublished
            $this->createPublishableComponent(null, 'is_draft');
        }
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
     * @Then it should include the draft resources instead of the published ones
     */
    public function itShouldIncludeTheDraftResourcesInsteadOfThePublishedOnes()
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
     * @Then it should include the published resources only
     */
    public function itShouldIncludeThePublishedResourcesOnly()
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

    private function createPublishableComponent(?\DateTime $publishedAt): PublishableComponent
    {
        $isPublished = $publishedAt <= new \Date();
        $reference = $isPublished ? 'is_published' : 'is_draft';
        $resource = new PublishableComponent($reference);
        $resource->setPublishedAt($publishedAt);
        $this->manager->persist($resource);
        $this->resources[] = $resource;

        return $resource;
    }

    private function getResourceIds(array $resources): array
    {
        return array_map(static function (PublishableComponent $component) {
            return $component->getId();
        }, $resources);
    }
}
