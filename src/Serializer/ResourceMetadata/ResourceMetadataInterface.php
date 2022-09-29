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

interface ResourceMetadataInterface
{
    public function isInit(): bool;

    public function getResourceMetadata(): ?self;

    public function getPageDataMetadata(): ?PageDataMetadata;

    public function setPageDataMetadata(PageDataMetadata $pageDataMetadata): void;

    public function getStaticComponent(): ?string;

    public function setStaticComponent(string $staticComponentIri): void;

    public function getCollection(): ?bool;

    public function setCollection(bool $collection): void;

    public function getPersisted(): ?bool;

    public function setPersisted(?bool $persisted): void;

    public function getPublishable(): ?ResourcePublishableMetadata;

    public function setPublishable(bool $published, ?string $publishedAt = null): void;

    public function getViolationList(): ?ConstraintViolationListInterface;

    public function setViolationList(?ConstraintViolationListInterface $violationList): void;

    public function getMediaObjects(): ?array;

    public function setMediaObjects(array $mediaObjects): void;
}
