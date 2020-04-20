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
use Behatch\Context\JsonContext as BehatchJsonContext;
use Behatch\Context\RestContext as BehatchRestContext;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\DraftComponent;

/**
 * @author Pierre Rebeilleau <pierre@les-tilleuls.coop>
 */
final class PublishableContext implements Context
{
    private DoctrineContext $doctrineContext;
    private BehatchRestContext $behatchRestContext;
    private ?BehatchJsonContext $behatchJsonContext;
    private ManagerRegistry $doctrine;

    public function __construct(DoctrineContext $doctrineContext, ManagerRegistry $doctrine)
    {
        $this->doctrineContext = $doctrineContext;
        $this->doctrine = $doctrine;
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
            $this->manager->flush();
            $object[$i] = $object;
        }
        for ($i = 0; $i < 2; ++$i) {
            $object = new DraftComponent();
            $object->name = "toto $i";
            $object->setPublishedRessource($objects[$i]);
            $this->manager->persist($object);
            $this->manager->flush();
        }

        $this->behatchRestContext->iSendARequestTo('GET', '/draft_components');
    }

    /**
     * @Then it should include the draft resources instead of the published ones
     */
    public function itShouldInculeTheDraftResourcesInsteadOfThePublishedOnes(): void
    {
        $this->behatchJsonContext->theJsonShouldBeValidAccordingToTheSchema(__DIR__ . '/../schema/draft.schema.json');
    }
}
