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

namespace Silverback\ApiComponentsBundle\Serializer\ResourceMetadata;

use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ResourceMetadata implements ResourceMetadataInterface
{
    // for page data
    #[Groups('cwa_resource:metadata')]
    private ?PageDataMetadata $pageDataMetadata = null;

    // for component position
    #[Groups('cwa_resource:metadata')]
    private ?string $staticComponent = null;

    // for component position
    #[Groups('cwa_resource:metadata')]
    private ?string $pageDataPath = null;

    // for component position
    #[Groups('cwa_resource:metadata')]
    private ?bool $isDynamicPosition = null;

    #[Groups('cwa_resource:metadata')]
    private ?bool $collection = null;

    #[Groups('cwa_resource:metadata')]
    private ?bool $persisted = null;

    #[Groups('cwa_resource:metadata')]
    private ?ResourcePublishableMetadata $publishable = null;

    #[Groups('cwa_resource:metadata')]
    private ?ConstraintViolationListInterface $violationList;

    #[Groups('cwa_resource:metadata')]
    private ?array $mediaObjects = null;

    #[Groups('cwa_resource:metadata')]
    private ?array $mercureSubscribeTopics = null;

    public function getResourceMetadata(): ?ResourceMetadataInterface
    {
        return $this;
    }

    public function getPageDataMetadata(): ?PageDataMetadata
    {
        return $this->pageDataMetadata;
    }

    public function setPageDataMetadata(PageDataMetadata $pageDataMetadata): void
    {
        $this->pageDataMetadata = $pageDataMetadata;
    }

    public function getStaticComponent(): ?string
    {
        return $this->staticComponent;
    }

    public function setStaticComponent(string $staticComponentIri): void
    {
        $this->staticComponent = $staticComponentIri;
    }

    public function getPageDataPath(): ?string
    {
        return $this->pageDataPath;
    }

    public function setPageDataPath(?string $pageDataPath): void
    {
        $this->pageDataPath = $pageDataPath;
    }

    public function getIsDynamicPosition(): ?bool
    {
        return $this->isDynamicPosition;
    }

    public function setIsDynamicPosition(?bool $isDynamicPosition): void
    {
        $this->isDynamicPosition = $isDynamicPosition;
    }

    public function getCollection(): ?bool
    {
        return $this->collection;
    }

    public function setCollection(bool $collection): void
    {
        $this->collection = $collection;
    }

    public function getPersisted(): ?bool
    {
        return $this->persisted;
    }

    public function setPersisted(?bool $persisted): void
    {
        $this->persisted = $persisted;
    }

    public function getPublishable(): ?ResourcePublishableMetadata
    {
        return $this->publishable;
    }

    public function setPublishable(bool $published, string $publishedAt = null): void
    {
        if ($this->publishable) {
            $this->publishable->published = $published;
            $this->publishable->publishedAt = $publishedAt;

            return;
        }
        $this->publishable = new ResourcePublishableMetadata($published, $publishedAt);
    }

    public function getViolationList(): ?ConstraintViolationListInterface
    {
        return $this->violationList;
    }

    public function setViolationList(?ConstraintViolationListInterface $violationList): void
    {
        $this->violationList = $violationList;
    }

    public function getMediaObjects(): ?array
    {
        return $this->mediaObjects;
    }

    public function setMediaObjects(array $mediaObjects): void
    {
        $this->mediaObjects = $mediaObjects;
    }

    public function getMercureSubscribeTopics(): ?array
    {
        return $this->mercureSubscribeTopics;
    }

    public function setMercureSubscribeTopics(?array $mercureSubscribeTopics): void
    {
        $this->mercureSubscribeTopics = $mercureSubscribeTopics;
    }
}
