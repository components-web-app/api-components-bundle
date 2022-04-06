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
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Symfony\Component\HttpClient\TraceableHttpClient;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class HttpClientContext implements Context
{
    private ?MinkContext $minkContext;
    private ?RestContext $restContext;
    private TraceableHttpClient $varnishHttpClient;

    public function __construct(TraceableHttpClient $varnishHttpClient)
    {
        $this->varnishHttpClient = $varnishHttpClient;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
    }

    /**
     * @Then the resource :resource_name should be purged from the cache
     */
    public function theResourceShouldBePurgedFromTheCache(string $resourceName)
    {
        $expectedIri = $this->restContext->resources[$resourceName];

        $purged = [];
        $requests = $this->varnishHttpClient->getTracedRequests();
        foreach ($requests as $request) {
            $xkeyHeaders = $request['options']['normalized_headers']['xkey'] ?? [];
            foreach ($xkeyHeaders as $xkeyHeader) {
                $iri = preg_replace('/^xkey\: /', '', $xkeyHeader);
                $purged[] = $iri;
                if ($iri === $expectedIri) {
                    return true;
                }
            }
        }
        throw new ExpectationException(sprintf('The resource %s was not found in any xkey headers sent to be purged. IRIs that were purged were `%s`', $expectedIri, implode('`, `', $purged)), $this->minkContext->getSession()->getDriver());
    }
}
