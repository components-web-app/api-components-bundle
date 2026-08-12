<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadablePublicUrl;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublish;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableTemporaryUrl;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PropertyAccess\PropertyAccess;

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
    private UploadableAttributeReader $uploadableAttributeReader;
    private FilesystemProvider $filesystemProvider;

    public function __construct(ManagerRegistry $doctrine, IriConverterInterface $iriConverter, UploadableFileManager $uploadableHelper, UploadableAttributeReader $uploadableAttributeReader, FilesystemProvider $filesystemProvider)
    {
        $this->manager = $doctrine->getManager();
        $this->iriConverter = $iriConverter;
        $this->uploadableHelper = $uploadableHelper;
        $this->uploadableAttributeReader = $uploadableAttributeReader;
        $this->filesystemProvider = $filesystemProvider;
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
        foreach (['dummy_uploadable', 'dummy_uploadable_draft', 'first_upload', 'second_upload'] as $key) {
            if (isset($this->restContext->resources[$key])) {
                try {
                    $this->uploadableHelper->deleteFiles($this->iriConverter->getResourceFromIri($this->restContext->resources[$key]));
                } catch (ItemNotFoundException $e) {
                    // we may have just deleted this resource
                }
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
     * @Given there is a draft DummyUploadableRequiredOnPublish
     */
    public function thereIsADraftDummyUploadableRequiredOnPublish(): void
    {
        $object = new DummyUploadableRequiredOnPublish();
        $object->setPublishedAt(null);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->resources['dummy_uploadable_draft'] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Given there is a draft DummyUploadableRequiredOnPublish with all files uploaded
     */
    public function thereIsADraftDummyUploadableRequiredOnPublishWithFiles(): void
    {
        $object = new DummyUploadableRequiredOnPublish();
        $object->setPublishedAt(null);
        $object->file = new File(__DIR__ . '/../assets/files/image.png');
        $object->preview = new File(__DIR__ . '/../assets/files/image.png');
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->resources['dummy_uploadable_draft'] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Given there is a draft DummyUploadableRequiredOnPublish with only the file uploaded
     */
    public function thereIsADraftDummyUploadableRequiredOnPublishWithFileOnly(): void
    {
        $object = new DummyUploadableRequiredOnPublish();
        $object->setPublishedAt(null);
        $object->file = new File(__DIR__ . '/../assets/files/image.png');
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->resources['dummy_uploadable_draft'] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Given there is a DummyUploadablePublicUrl
     */
    public function thereIsADummyUploadablePublicUrl(): void
    {
        $object = new DummyUploadablePublicUrl();
        $object->file = new File(__DIR__ . '/../assets/files/image.png');
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->resources['dummy_uploadable'] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Given there is a DummyUploadableTemporaryUrl
     */
    public function thereIsADummyUploadableTemporaryUrl(): void
    {
        $object = new DummyUploadableTemporaryUrl();
        $object->file = new File(__DIR__ . '/../assets/files/image.png');
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->resources['dummy_uploadable'] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Given there is a DummyUploadable with the file :file saved as :name
     */
    public function thereIsADummyUploadableWithTheFileSavedAs(string $file, string $name): void
    {
        $object = new DummyUploadable();
        $object->file = new File(__DIR__ . '/../assets/files/' . $file);
        $this->uploadableHelper->persistFiles($object);
        $this->manager->persist($object);
        $this->manager->flush();
        $this->restContext->resources[$name] = $this->iriConverter->getIriFromResource($object);
    }

    /**
     * @Then the JSON node :node should start with :prefix
     */
    public function theJsonNodeShouldStartWith(string $node, string $prefix): void
    {
        $this->behatchJsonContext->theJsonNodeShouldMatch($node, \sprintf('/^%s/', preg_quote($prefix, '/')));
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
     * @Then the resource :name should have a filename matching :pattern
     */
    public function theResourceShouldHaveAFilenameMatching(string $name, string $pattern): void
    {
        // Manual checks + plain exceptions: PHPUnit 11's failure-message Exporter
        // relies on its TextUI Configuration Registry, which Behat never bootstraps,
        // so a failing Assert::assert* fatals while rendering rather than reporting.
        $filename = $this->getUploadableResourceByName($name)->getFilename();
        if (null === $filename || 1 !== preg_match($pattern, $filename)) {
            throw new \RuntimeException(\sprintf('Expected the filename for "%s" to match %s, got "%s".', $name, $pattern, $filename ?? 'null'));
        }
    }

    /**
     * @Then the resource :name should have a different filename to the resource :other
     */
    public function theResourceShouldHaveADifferentFilenameToTheResource(string $name, string $other): void
    {
        $first = $this->getUploadableResourceByName($name)->getFilename();
        $second = $this->getUploadableResourceByName($other)->getFilename();
        if (null === $first || null === $second) {
            throw new \RuntimeException(\sprintf('Both resources must have a filename ("%s"=%s, "%s"=%s).', $name, $first ?? 'null', $other, $second ?? 'null'));
        }
        if ($first === $second) {
            throw new \RuntimeException(\sprintf('Resources "%s" and "%s" share the filename "%s"; one would overwrite the other.', $name, $other, $first));
        }
    }

    /**
     * @Then the file for the resource :name should exist in its configured filestore
     */
    public function theFileForTheResourceShouldExistInItsConfiguredFilestore(string $name): void
    {
        $item = $this->getUploadableResourceByName($name);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $configuredProperties = $this->uploadableAttributeReader->getConfiguredProperties($item, true);
        $checked = 0;
        foreach ($configuredProperties as $fieldConfiguration) {
            $filePath = $propertyAccessor->getValue($item, $fieldConfiguration->property);
            if (empty($filePath)) {
                continue;
            }
            ++$checked;
            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
            if (!$filesystem->fileExists($filePath)) {
                throw new \RuntimeException(\sprintf('Expected file "%s" to exist in adapter "%s" but it does not.', $filePath, $fieldConfiguration->adapter));
            }
        }
        if (0 === $checked) {
            throw new \RuntimeException(\sprintf('The resource "%s" has no stored file to check.', $name));
        }
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
