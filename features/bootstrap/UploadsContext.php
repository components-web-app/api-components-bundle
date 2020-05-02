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
use ApiPlatform\Core\Exception\ItemNotFoundException;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
use Silverback\ApiComponentsBundle\Uploadable\UploadableHelper;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadsContext implements Context
{
    private ?RestContext $restContext;
    private ObjectManager $manager;
    private IriConverterInterface $iriConverter;
    private UploadableHelper $uploadableHelper;

    public function __construct(ManagerRegistry $doctrine, IriConverterInterface $iriConverter, UploadableHelper $uploadableHelper)
    {
        $this->manager = $doctrine->getManager();
        $this->iriConverter = $iriConverter;
        $this->uploadableHelper = $uploadableHelper;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
    }

    /**
     * @AfterScenario
     */
    public function removeFile(): void
    {
        if (isset($this->restContext->components['dummy_uploadable'])) {
            try {
                $this->uploadableHelper->deleteFiles($this->iriConverter->getItemFromIri($this->restContext->components['dummy_uploadable']));
            } catch (ItemNotFoundException $e) {
                // we may heva just deleted this resource 'dummy_uploadable'
            }
        }
    }

    /**
     * @Given there is a DummyUploadableWithImagineFilters
     */
    public function thereIsADummyUploadableWithImagineFilters(): void
    {
        $object = new DummyUploadableWithImagineFilters();
        $object->file = new File(__DIR__ . '/../assets/files/image.png');
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->components['dummy_uploadable'] = $this->iriConverter->getIriFromItem($object);
    }
}
