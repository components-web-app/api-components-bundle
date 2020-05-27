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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource
 * @UniqueEntity(fields={"reference"}, message="There is already a ComponentCollection resource with that reference.")
 */
class ComponentCollection
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotBlank(message="Please enter a reference.")
     */
    public string $reference;

    /**
     * @var Collection|Layout[]
     */
    public $layouts;

    /**
     * @var Collection|Page[]
     */
    public Collection $pages;

    /**
     * @var Collection|AbstractComponent[]
     */
    public Collection $components;

    /**
     * @var Collection|ComponentPosition[]
     */
    public Collection $componentPositions;

    public ?Collection $allowedComponents = null;

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
        $this->layouts->add($layout);

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
        $this->pages->add($page);

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
        $this->components->add($component);

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
        $this->componentPositions->add($componentPosition);

        return $this;
    }

    public function setAllowedComponents(iterable $allowedComponents): self
    {
        $this->allowedComponents = new ArrayCollection();
        foreach ($allowedComponents as $componentIri) {
            $this->addAllowedComponent($componentIri);
        }

        return $this;
    }

    public function addAllowedComponent(string $allowedComponent): self
    {
        $this->allowedComponents->add($allowedComponent);

        return $this;
    }
}
