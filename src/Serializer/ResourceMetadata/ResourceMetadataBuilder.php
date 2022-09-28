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
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ResourceMetadataBuilder implements ResourceMetadataInterface
{
    public ?ResourceMetadata $resourceMetadata = null;

    public function isInit(): bool
    {
        return null !== $this->resourceMetadata;
    }

    public function init(): void
    {
        $this->resourceMetadata = new ResourceMetadata();
    }

    public function destroy(): void
    {
        $this->resourceMetadata = null;
    }

    public function getResourceMetadata(): ?ResourceMetadataInterface
    {
        return $this->resourceMetadata;
    }

    public function getPageDataMetadata(): ?PageDataMetadata
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setPageDataMetadata(PageDataMetadata $pageDataMetadata): void
    {
        $this->setValue(__FUNCTION__, [$pageDataMetadata]);
    }

    public function getStaticComponent(): ?string
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setStaticComponent(string $staticComponentIri): void
    {
        $this->setValue(__FUNCTION__, [$staticComponentIri]);
    }

    public function getCollection(): ?bool
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setCollection(bool $collection): void
    {
        $this->setValue(__FUNCTION__, [$collection]);
    }

    public function getPersisted(): ?bool
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setPersisted(?bool $persisted): void
    {
        $this->setValue(__FUNCTION__, [$persisted]);
    }

    public function getPublishable(): ?ResourcePublishableMetadata
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setPublishable(bool $published, ?string $publishedAt = null): void
    {
        $this->setValue(__FUNCTION__, [$published, $publishedAt]);
    }

    public function getViolationList(): ?ConstraintViolationListInterface
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setViolationList(?ConstraintViolationListInterface $violationList): void
    {
        $this->setValue(__FUNCTION__, [$violationList]);
    }

    public function getMediaObjects(): ?array
    {
        return $this->getValue(__FUNCTION__);
    }

    public function setMediaObjects(array $mediaObjects): void
    {
        $this->setValue(__FUNCTION__, [$mediaObjects]);
    }

    private function getValue(string $methodName)
    {
        return $this->resourceMetadata?->{$methodName}();
    }

    private function setValue(string $methodName, array $value): void
    {
        $this->resourceMetadata?->{$methodName}(...$value);
    }
}
