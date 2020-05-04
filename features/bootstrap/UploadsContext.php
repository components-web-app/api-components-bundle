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
use Behatch\Context\JsonContext as BehatchJsonContext;
use Behatch\Context\RestContext as BehatchRestContext;
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
    private ?BehatchJsonContext $behatchJsonContext;
    private ?BehatchRestContext $behatchRestContext;
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
        $this->behatchRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->behatchJsonContext = $scope->getEnvironment()->getContext(BehatchJsonContext::class);
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

    /**
     * @When I request the download endpoint
     */
    public function iRequestTheDownloadEndpoint()
    {
        $endpoint = $this->restContext->components['dummy_uploadable'] . '/download/file';

        return $this->behatchRestContext->iSendARequestTo('GET', $endpoint);
    }

    /**
     * @Then the JSON node :node should be a valid download link for the component :component
     */
    public function thenTheJsonNodeShoudBeAValidDownloadLinkForTheComponent($node, $component)
    {
        $endpoint = 'http://example.com' . $this->restContext->components['dummy_uploadable'] . '/download/file';
        $this->behatchJsonContext->theJsonNodeShouldBeEqualToTheString($node, $endpoint);
    }
}
