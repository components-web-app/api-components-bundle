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
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithPagination;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CollectionContext implements Context
{
    private ObjectManager $manager;
    private IriConverterInterface $iriConverter;
    private ?RestContext $restContext;

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
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
    }

    /**
     * @Given there are :number DummyComponent resources
     */
    public function thereAreDummyComponentResources(int $number)
    {
        for ($i = 0; $i < $number; ++$i) {
            $component = new DummyComponent();
            $this->manager->persist($component);
            $this->restContext->resources['dummy_component_' . $i] = $this->iriConverter->getIriFromResource($component);
        }
        $this->manager->flush();
    }

    /**
     * @Given there are :number DummyResourceWithPagination resources
     */
    public function thereAreDummyResourceWithPaginationResources(int $number)
    {
        for ($i = 0; $i < $number; ++$i) {
            $component = new DummyResourceWithPagination();
            $this->manager->persist($component);
        }
        $this->manager->flush();
    }

    /**
     * @Given there are :number DummyResourceWithFilters resources
     */
    public function thereAreDummyResourceWithFiltersResources(int $number)
    {
        for ($i = 0; $i < $number; ++$i) {
            $component = new DummyResourceWithFilters();
            $component->reference = 'reference ' . $i;
            $this->manager->persist($component);
        }
        $this->manager->flush();
    }

    /**
     * @Given /^there is a Collection resource(?: with the resource IRI "(.*)")?$/
     */
    public function thereIsACollectionResource(string $iri = '/component/dummy_components')
    {
        $component = new Collection();
        $component->setResourceIri($iri);
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['collection'] = $this->iriConverter->getIriFromResource($component);
    }

    /**
     * @Given /^there is a Collection resource(?: with the resource IRI "(.*)")? and default query string parameters$/
     */
    public function thereIsACollectionResourceWithDefaultQueryStringParameters(string $iri = '/component/dummy_components')
    {
        $component = new Collection();
        $component->setDefaultQueryParameters(
            [
                'perPage' => 40,
                'reference' => '1',
            ]
        );
        $component->setResourceIri($iri);
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['collection'] = $this->iriConverter->getIriFromResource($component);
    }
}
