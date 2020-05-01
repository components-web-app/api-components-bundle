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

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyFile;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadsContext implements Context
{
    private ?RestContext $restContext;
    private ObjectManager $manager;
    private IriConverterInterface $iriConverter;

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
     * @Given there is a DummyFile
     */
    public function thereIsADummyFile(): void
    {
        $object = new DummyFile();
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->components['dummy_file'] = $this->iriConverter->getIriFromItem($resource);
    }
}
