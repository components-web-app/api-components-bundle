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

use ApiPlatform\Api\IriConverterInterface;
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
use Silverback\ApiComponentsBundle\Entity\Core\ComponentCollection;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableCustomComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithSecurityGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithValidation;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithValidationCustomGroup;

/**
 * @author Pierre Rebeilleau <pierre@les-tilleuls.coop>
 */
final class PublishableContext implements Context
{
    private ?BehatchRestContext $behatchRestContext;
    private ?BehatchJsonContext $behatchJsonContext;
    private ?JsonContext $jsonContext;
    private ?DoctrineContext $doctrineContext;
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
        $this->doctrineContext = $scope->getEnvironment()->getContext(DoctrineContext::class);
    }

    /**
     * @AfterScenario
     */
    public function resetResources()
    {
        $this->resources = [];
        $this->publishedResourcesWithoutDrafts = [];
    }

    /**
     * @Transform /^(false|true)$/
     */
    public function castFalseToBoolean($string): bool
    {
        return 'true' === $string;
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

            $this->thereIsAPublishableResource(null, false);
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
        $draft = $this->thereIsAPublishableResource($publishAt, false, true);
        $draft->setPublishedResource($publishedRecently);
        $this->manager->flush();
    }

    /**
     * @Given /^there is a draft for "([^"]*)"(?: set to publish at "(.*)"|)$/
     */
    public function thereIsADraftFor(string $publishedComponent, ?string $publishDate = null): void
    {
        $component = $this->iriConverter->getItemFromIri($this->restContext->resources[$publishedComponent]);
        if (!$component instanceof DummyPublishableComponent) {
            throw new \RuntimeException(sprintf('The resource named `%s` is not a DummyPublishableComponent', $publishedComponent));
        }
        $publishAt = $publishDate ? (new \DateTime($publishDate))->format('Y-m-d H:i:s') : null;
        $draft = $this->thereIsAPublishableResource($publishAt, false, true);
        $draft->setPublishedResource($component);
        $this->manager->flush();
    }

    /**
     * @Given there is a ComponentPosition with the resource :resource
     */
    public function thereIsAComponentPositionWithTheResource(string $resource)
    {
        $collection = new ComponentCollection();
        $collection
            ->setReference('dummy_collection')
            ->setLocation('nowhere')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setModifiedAt(new \DateTime());
        $this->manager->persist($collection);

        $position = new ComponentPosition();
        $position
            ->setComponentCollection($collection)
            ->setComponent($this->iriConverter->getItemFromIri($this->restContext->resources[$resource]))
            ->setSortValue(1)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setModifiedAt(new \DateTime());
        $this->manager->persist($position);
        $this->manager->flush();
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromItem($position);
    }

    /**
     * @Given there is a DummyPublishableComponent in PageData and a Position
     */
    public function thereIsADummyPublishableComponentInPageDataAndAPosition()
    {
        $this->doctrineContext->abstractThereIsADummyComponentInPageDataAndAPosition($this->createPublishableComponent(new \DateTime('-10 seconds')));
    }

    /**
     * @Given /^there is a publishable resource(?: set to publish at "(.*)"|)$/
     */
    public function thereIsAPublishableResource(?string $publishDate = null, bool $flush = true, bool $forceDraft = false): DummyPublishableComponent
    {
        $object = $this->createPublishableComponent($publishDate ? new \DateTime($publishDate) : null, $forceDraft);
        $flush && $this->manager->flush();

        return $object;
    }

    /**
     * @Given /^there is a DummyPublishableWithSecurityGroups resource(?: set to publish at "(.*)"|)$/
     */
    public function thereIsADummyPublishableWithSecurityGroupsResource(?string $publishDate = null, bool $flush = true, bool $forceDraft = false): DummyPublishableWithSecurityGroups
    {
        $object = $this->createPublishableComponent($publishDate ? new \DateTime($publishDate) : null, $forceDraft, new DummyPublishableWithSecurityGroups());
        $flush && $this->manager->flush();

        return $object;
    }

    /**
     * @Given /^there is a DummyPublishableWithValidation resource(?: set to publish at "(.*)"|)$/
     */
    public function thereIsADummyPublishableWithValidation(?string $publishDate = null, bool $flush = true, bool $forceDraft = false): DummyPublishableWithValidation
    {
        $object = $this->createPublishableComponent($publishDate ? new \DateTime($publishDate) : null, $forceDraft, new DummyPublishableWithValidation());
        $flush && $this->manager->flush();

        return $object;
    }

    /**
     * @Given /^there is a DummyPublishableWithValidationCustomGroup resource(?: set to publish at "(.*)"|)$/
     */
    public function thereIsADummyPublishableWithValidationCustomGroup(?string $publishDate = null, bool $flush = true, bool $forceDraft = false): DummyPublishableWithValidation
    {
        $object = $this->createPublishableComponent($publishDate ? new \DateTime($publishDate) : null, $forceDraft, new DummyPublishableWithValidationCustomGroup());
        $flush && $this->manager->flush();

        return $object;
    }

    /**
     * @Given /^there is a custom publishable resource(?: set to publish at "(.*)"|)$/
     */
    public function thereIsACustomPublishableResource(?string $publishDate = null, bool $flush = true): DummyPublishableCustomComponent
    {
        $publishedAt = $publishDate ? new \DateTime($publishDate) : null;
        $isPublished = null !== $publishedAt && $publishedAt <= new \DateTime();
        $resource = new DummyPublishableCustomComponent();
        $resource->reference = $isPublished ? 'is_published' : 'is_draft';
        $resource->setCustomPublishedAt($publishedAt);
        $this->manager->persist($resource);
        $flush && $this->manager->flush();

        $this->resources[] = $resource;
        $componentKey = sprintf('publishable_%s', $isPublished ? 'published' : 'draft');
        $this->restContext->resources[$componentKey] = $this->iriConverter->getIriFromItem($resource);

        return $resource;
    }

    /**
     * @When I create a resource
     */
    public function iCreateAResource(): void
    {
        $this->behatchRestContext->iSendARequestTo(
            'POST',
            '/publishable_components',
            new PyStringNode(
                ['{
                "name": "John Doe"
            }'],
                1
            ),
        );
    }

    /**
     * @Then the response should include the draft resources instead of the published ones
     */
    public function theResponseShouldIncludeTheDraftResourcesInsteadOfThePublishedOnes(): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        $items = $response['hydra:member'];
        $draftResources = array_filter(
            $this->resources,
            static function (DummyPublishableComponent $component) {
                return 'is_draft' === $component->reference;
            }
        );

        $expectedTotal = \count($draftResources) + \count($this->publishedResourcesWithoutDrafts);

        if ($expectedTotal !== ($receivedTotal = \count($items))) {
            throw new ExpectationException(sprintf('Expected %d resources but received %d', $expectedTotal, $receivedTotal), $this->minkContext->getSession()->getDriver());
        }

        $expectedPublishedResourceIds = $this->getResourceIds($this->publishedResourcesWithoutDrafts);

        foreach ($items as $item) {
            Assert::assertArrayNotHasKey('draftResource', $item, 'No published resources which have a draft available should be returned but there is a resource with a draftResource property indicating there is a draft resource available for this published item.');

            if ('is_draft' !== $item['reference'] && !\in_array($item['@id'], $expectedPublishedResourceIds, true)) {
                throw new ExpectationException('Received an unexpected item in the response: ' . json_encode($item, \JSON_THROW_ON_ERROR, 512), $this->minkContext->getSession()->getDriver());
            }
        }
    }

    /**
     * @Then the response should include the published resources only
     */
    public function theResponseShouldIncludeThePublishedResourcesOnly(): array
    {
        $response = $this->jsonContext->getJsonAsArray();
        $items = $response['hydra:member'];

        $publishedResources = array_filter(
            $this->resources,
            static function (DummyPublishableComponent $component) {
                return 'is_published' === $component->reference;
            }
        );

        $expectedTotal = \count($publishedResources);

        Assert::assertEquals($expectedTotal, $receivedTotal = \count($items), sprintf('Expected %d resources but received %d', $expectedTotal, $receivedTotal));

        foreach ($items as $item) {
            Assert::assertEquals('is_published', $item['reference'], 'Received an unexpected item in the response: ' . json_encode($item, \JSON_THROW_ON_ERROR, 512));
        }

        return $items;
    }

    /**
     * @Then the response should include the published resources only without the draftResources key
     */
    public function theResponseShouldIncludeThePublishedResourcesOnlyWithoutTheDraftResourcesKey(): void
    {
        $items = $this->theResponseShouldIncludeThePublishedResourcesOnly();
        foreach ($items as $item) {
            Assert::assertArrayNotHasKey('draftResource', $item, 'A draft resource was included in the response of a published resource');
        }
    }

    /**
     * @Then the component position should have the component :reference
     */
    public function theComponentPositionShouldHaveTheComponent(string $reference)
    {
        $response = $this->jsonContext->getJsonAsArray();
        Assert::assertEquals($this->restContext->resources[$reference], $response['component'], sprintf('The component position was expected to have the component %s but %s was returned', $this->restContext->resources[$reference], $response['component']));
    }

    /**
     * @return object|DummyPublishableComponent|DummyPublishableWithValidation|DummyPublishableWithValidationCustomGroup|DummyPublishableWithSecurityGroups
     */
    private function createPublishableComponent(?\DateTime $publishedAt, bool $forceDraft = false, object $resource = null)
    {
        $isPublished = !$forceDraft && (null !== $publishedAt && $publishedAt <= new \DateTime());
        $resource = $resource ?: new DummyPublishableComponent();
        $resource->reference = $isPublished ? 'is_published' : 'is_draft';
        $resource->setPublishedAt($publishedAt);
        $this->manager->persist($resource);
        $this->resources[] = $resource;

        $componentKey = sprintf('publishable_%s', $isPublished ? 'published' : 'draft');
        $this->restContext->resources[$componentKey] = $this->iriConverter->getIriFromItem($resource);

        return $resource;
    }

    private function getResourceIds(array $resources): array
    {
        return array_map(
            function (DummyPublishableComponent $component) {
                return $this->iriConverter->getIriFromItem($component);
            },
            $resources
        );
    }
}
