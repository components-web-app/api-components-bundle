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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Silverback\Publishable(fieldName="customPublishedAt", associationName="customPublishedResource", reverseAssociationName="customDraftResource")
 * @ApiResource
 * @ORM\Entity
 */
class DummyPublishableCustomComponent extends AbstractComponent
{
    /**
     * @var string a reference for this component
     *
     * @ORM\Column
     * @Assert\NotBlank(groups={"PublishableComponent:published"})
     */
    public string $reference = '';

    private ?\DateTimeInterface $customPublishedAt = null;

    private ?self $customPublishedResource = null;

    private ?self $customDraftResource = null;

    /** @return static */
    public function setCustomPublishedAt(?\DateTimeInterface $customPublishedAt)
    {
        $this->customPublishedAt = $customPublishedAt;

        return $this;
    }

    public function getCustomPublishedAt(): ?\DateTimeInterface
    {
        return $this->customPublishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->customPublishedAt && new \DateTimeImmutable() >= $this->customPublishedAt;
    }

    /** @return static */
    public function setCustomPublishedResource($customPublishedResource)
    {
        $this->customPublishedResource = $customPublishedResource;

        return $this;
    }

    public function getCustomPublishedResource(): ?self
    {
        return $this->customPublishedResource;
    }

    public function getCustomDraftResource(): ?self
    {
        return $this->customDraftResource;
    }
}
