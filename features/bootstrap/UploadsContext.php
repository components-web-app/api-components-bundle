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

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\ExpectationException;
use Behatch\Context\JsonContext as BehatchJsonContext;
use Behatch\Context\RestContext as BehatchRestContext;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Assert;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
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
    private UploadableFileManager $uploadableHelper;

    public function __construct(ManagerRegistry $doctrine, IriConverterInterface $iriConverter, UploadableFileManager $uploadableHelper)
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
        if (isset($this->restContext->resources['dummy_uploadable'])) {
            try {
                $this->uploadableHelper->deleteFiles($this->iriConverter->getResourceFromIri($this->restContext->resources['dummy_uploadable']));
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
        $this->restContext->resources['dummy_uploadable'] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Given /^there is a( draft)? DummyUploadableAndPublishable( with a draft)??$/
     */
    public function thereIsADummyUploadableAndPublishable(bool $isDraft = false, bool $associatedDraft = false): DummyUploadableAndPublishable
    {
        $object = new DummyUploadableAndPublishable();
        $object->setPublishedAt($isDraft ? null : new \DateTime());
        $object->file = new File(__DIR__ . '/../assets/files/image.png');
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $key = $isDraft ? 'dummy_uploadable_draft' : 'dummy_uploadable';
        $this->restContext->resources[$key] = $this->iriConverter->getIriFromResource($object);

        if ($associatedDraft) {
            $draftObject = $this->thereIsADummyUploadableAndPublishable(true, false);
            $draftObject->setPublishedResource($object);
            $this->manager->flush();
        }

        return $object;
    }

    /**
     * @Given the resource :resource has a file :file
     */
    public function theResourceHasAFile(string $resourceName, string $file)
    {
        $resource = $this->iriConverter->getResourceFromIri($this->restContext->resources[$resourceName]);
        $resource->setFilename($file);
        $this->manager->flush();
    }

    /**
     * @When /^I request the download endpoint(?: with the postfix "(.+)")?$/
     */
    public function iRequestTheDownloadEndpoint(?string $postfix = null)
    {
        $endpoint = $this->restContext->resources['dummy_uploadable'] . '/download/file';
        if ($postfix) {
            $endpoint .= $postfix;
        }

        return $this->behatchRestContext->iSendARequestTo('GET', $endpoint);
    }

    /**
     * @Then the JSON node :node should be a valid download link for the resource :resource
     */
    public function thenTheJsonNodeShoudBeAValidDownloadLinkForTheResource($node, $resource)
    {
        $endpoint = 'http://example.com' . $this->restContext->resources[$resource] . '/download/file';
        $this->behatchJsonContext->theJsonNodeShouldBeEqualToTheString($node, $endpoint);
    }

    /**
     * @Then the resource :name should have an uploaded file
     */
    public function theResourceShouldHaveAnUploadedFile(string $name): void
    {
        $item = $this->getUploadableResourceByName($name);
        Assert::assertNotNull($item->getFilename());
    }

    /**
     * @Then the resource :name should have :count component positions
     */
    public function theResourceShouldHaveComponentPositions(string $name, int $count): void
    {
        $item = $this->getUploadableResourceByName($name);
        Assert::assertCount($count, $item->getComponentPositions());
    }

    /**
     * @Then the resource :name should not have an uploaded file
     */
    public function theResourceShouldNotHaveAnUploadedFile(string $name): void
    {
        $item = $this->getUploadableResourceByName($name);
        Assert::assertNull($item->getFilename());
    }

    private function getUploadableResourceByName(string $name)
    {
        $this->manager->clear();
        try {
            $iri = $this->restContext->resources[$name];

            /* @var UploadableTrait $item */
            return $this->iriConverter->getResourceFromIri($iri);
        } catch (ItemNotFoundException $exception) {
            throw new ExpectationException(\sprintf('The resource %s cannot be found anymore', $iri), $this->minkContext->getSession()->getDriver());
        }
    }
}
