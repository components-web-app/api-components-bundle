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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Timestamped]
#[ApiResource(
    denormalizationContext: ['groups' => ['ComponentCollection:write']],
    mercure: true,
    normalizationContext: ['groups' => ['ComponentCollection:read']]
)]
#[UniqueEntity(fields: ['reference'], message: 'There is already a ComponentCollection resource with that reference.')]
class ComponentCollection
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * #[Assert\NotBlank(message="The reference cannot be blank.")]
     * #[Groups({"ComponentCollection:read", "ComponentCollection:write"})].
     */
    public ?string $reference = null;

    /**
     * #[Assert\NotBlank(message="The location cannot be blank.")]
     * #[Groups({"ComponentCollection:read", "ComponentCollection:write"})].
     */
    public ?string $location = null;

    /**
     * @var Collection|Layout[]
     *                          #[Groups({"ComponentCollection:read", "ComponentCollection:write"})]
     */
    public Collection $layouts;

    /**
     * @var Collection|Page[]
     *                        #[Groups({"ComponentCollection:read", "ComponentCollection:write"})]
     */
    public Collection $pages;

    /**
     * @var Collection|AbstractComponent[]
     *                                     #[Groups({"ComponentCollection:read", "ComponentCollection:write"})]
     */
    public Collection $components;

    /**
     * @var Collection|ComponentPosition[]
     *                                     #[Groups({"ComponentCollection:read", "ComponentCollection:write"})]
     */
    public Collection $componentPositions;

    /**
     * @var string[]|null
     *                    #[Groups({"ComponentCollection:read", "ComponentCollection:write"})]
     */
    public ?array $allowedComponents = null;

    public function __construct()
    {
        $this->layouts = new ArrayCollection();
        $this->pages = new ArrayCollection();
        $this->components = new ArrayCollection();
        $this->componentPositions = new ArrayCollection();
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function setLayouts(iterable $layouts): self
    {
        $this->layouts = new ArrayCollection();
        foreach ($layouts as $layout) {
            $this->addLayout($layout);
        }

        return $this;
    }

    public function addLayout(Layout $layout): self
    {
        if (!$this->layouts->contains($layout)) {
            $this->layouts->add($layout);
            $layout->addComponentCollection($this);
        }

        return $this;
    }

    public function setPages(iterable $pages): self
    {
        $this->pages = new ArrayCollection();
        foreach ($pages as $page) {
            $this->addPage($page);
        }

        return $this;
    }

    public function addPage(Page $page): self
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->addComponentCollection($this);
        }

        return $this;
    }

    public function setComponents(iterable $components): self
    {
        $this->components = new ArrayCollection();
        foreach ($components as $component) {
            $this->addComponent($component);
        }

        return $this;
    }

    public function addComponent(AbstractComponent $component): self
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->addComponentCollection($this);
        }

        return $this;
    }

    public function setComponentPositions(iterable $componentPositions): self
    {
        $this->componentPositions = new ArrayCollection();
        foreach ($componentPositions as $componentPosition) {
            $this->addComponentPosition($componentPosition);
        }

        return $this;
    }

    public function addComponentPosition(ComponentPosition $componentPosition): self
    {
        if (!$this->componentPositions->contains($componentPosition)) {
            $this->componentPositions->add($componentPosition);
        }

        return $this;
    }

    public function setAllowedComponents(iterable $allowedComponents): self
    {
        $this->allowedComponents = [];
        foreach ($allowedComponents as $componentIri) {
            $this->addAllowedComponent($componentIri);
        }

        return $this;
    }

    public function addAllowedComponent(string $allowedComponent): self
    {
        if (null === $this->allowedComponents) {
            $this->allowedComponents = [];
        }
        $this->allowedComponents[] = $allowedComponent;

        return $this;
    }
}
