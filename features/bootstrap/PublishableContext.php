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

/**
 * @author Pierre Rebeilleau <pierre@les-tilleuls.coop>
 */
final class PublishableContext implements Context
{
    private DoctrineContext $doctrineContext;
    private ?BehatchJsonContext $behatchJsonContext;
    private JsonContext $jsonContext;

    public function __construct(DoctrineContext $doctrineContext)
    {
        $this->doctrineContext = $doctrineContext;
    }
    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->behatchJsonContext = $scope->getEnvironment()->getContext(BehatchJsonContext::class);
    }

    /**
     * @When I get a collection of published resources with draft resources available
     */
    public function toto(): void{
        // TODO Create draft components objects with draft resources available
        // TODO Send a get request to '/draft_components'
    }
    /**
     * @Then it should include the draft resources instead of the published ones
     */
    public function tata(): void{
        $this->behatchJsonContext->theJsonShouldBeValidAccordingToTheSchema(__DIR__.'/../schema/draft.schema.json');
    }
}
