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
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\DraftComponent;

/**
 * @author Pierre Rebeilleau <pierre@les-tilleuls.coop>
 */
final class PublishableContext implements Context
{
    private DoctrineContext $doctrineContext;
    private ?BehatchRestContext $behatchRestContext;
    private ?BehatchJsonContext $behatchJsonContext;
    private ?JsonContext $jsonContext;
    private ManagerRegistry $doctrine;
    private ObjectManager $manager;

    public function __construct(DoctrineContext $doctrineContext, ManagerRegistry $doctrine)
    {
        $this->doctrineContext = $doctrineContext;
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->behatchJsonContext = $scope->getEnvironment()->getContext(BehatchJsonContext::class);
        $this->behatchRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
    }

    /**
     * @When I get a collection of published resources with draft resources available
     */
    public function iGetACollectionOfPublishedResourcesWithDraftResourcesAvailable(): void
    {
        $objects = [];

        for ($i = 0; $i < 5; ++$i) {
            $object = new DraftComponent();
            $object->name = "toto $i";
            $object->setPublishedAt(new \DateTime());
            $this->manager->persist($object);
            $object[$i] = $object;
        }
        $this->manager->flush();

        for ($i = 0; $i < 2; ++$i) {
            $object = new DraftComponent();
            $object->name = "toto $i";
            $object->setPublishedResource($objects[$i]);
            $this->manager->persist($object);
        }
        $this->manager->flush();

        $this->behatchRestContext->iSendARequestTo('GET', '/draft_components');
    }

    /**
     * @When I get a collection of published resources with draft resources available and published=true query filter
     */
    public function iGetACollectionOfPublishedResourcesWithDraftResourcesAvailableAndPublishedIsEqualTrueQueryFilter(): void
    {
        $objects = [];

        for ($i = 0; $i < 5; ++$i) {
            $object = new DraftComponent();
            $object->name = "toto $i";
            $object->setPublishedAt(new \DateTime());
            $this->manager->persist($object);
            $object[$i] = $object;
        }
        $this->manager->flush();

        for ($i = 0; $i < 2; ++$i) {
            $object = new DraftComponent();
            $object->name = "toto $i";
            $object->setPublishedResource($objects[$i]);
            $this->manager->persist($object);
        }
        $this->manager->flush();

        $this->behatchRestContext->iSendARequestTo('GET', '/draft_components?published=true');
    }

    /**
     * @When I create a resource
     */
    public function iCreateAResource(): void
    {
        $this->behatchRestContext->iSendARequestTo('POST', '/draft_components', new PyStringNode(
            ['{
                "name": "John Doe"
            }'],
            1
        ), );
    }

    /**
     * @When I create a resource with an active publication date
     */
    public function iCreateAResourceWithAnActivePublicationDate(): void
    {
        $this->behatchRestContext->iSendARequestTo('POST', '/draft_components', new PyStringNode(
            ['{
                "name": "John Doe",
                "publishedAt": "2020-04-19 07:32:16"
            }'],
            1
        ), );
    }

    /**
     * @When I create a resource with a future publication date
     */
    public function iCreateAResourceWithAFuturePublicationDate(): void
    {
        $this->behatchRestContext->iSendARequestTo('POST', '/draft_components', new PyStringNode(
            ['{
                "name": "John Doe",
                "publishedAt": "2020-05-19 07:32:16"
            }'],
            1
        ), );
    }

    /**
     * @Then it should include the draft resources instead of the published ones
     */
    public function itShouldIncludeTheDraftResourcesInsteadOfThePublishedOnes(): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchemaFile('draft.schema.json');
    }

    /**
     * @Then it should include the published resources only
     */
    public function itShouldIncludeThePublishedResourcesOnly(): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchemaFile('/published.schema.json');
    }

    /**
     * @Then it should not include the draft resources
     */
    public function itShouldNotIncludeTheDraftResources(): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchemaFile('no_draft.schema.json');
    }

    /**
     * @Then I should have the draft resource returned
     */
    public function iShouldHaveTheDraftResourceReturned(): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchemaFile('single_draft.schema.json');
    }

    /**
     * @Then I should have the published resource returned
     */
    public function iShouldHaveThePublishedResourceReturned(): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchemaFile('single_published.schema.json');
    }

    /**
     * @Then I should have the published resource returned and the publication date is automatically set
     */
    public function iShouldHaveThePublishedResourceReturnedAndThePublicationDateIsAutomaticallySet(): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchemaFileAndTheDateIsCreated('single_dateCreated_published.schema.json');
    }
}
