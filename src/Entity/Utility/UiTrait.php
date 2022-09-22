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

namespace Silverback\ApiComponentsBundle\Entity\Utility;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @internal
 */
trait UiTrait
{
    #[Orm\Column(nullable: true)]
    public ?string $uiComponent = null;

    #[Orm\Column(type: 'json', nullable: true)]
    public ?array $uiClassNames = null;

    /**
     * @var Collection|ComponentGroup[]
     *                                  Todo: CHECK THIS FIX, dirrrrrrty for form serialization
     */
    #[Orm\ManyToMany(targetEntity: ComponentGroup::class)]
    #[Groups(['Route:manifest:read', 'AbstractComponent:cwa_resource:read'])]
    private Collection $componentGroups;

    public function __construct()
    {
        $this->initComponentGroups();
    }

    private function initComponentGroups(): void
    {
        $this->componentGroups = new ArrayCollection();
    }

    /**
     * @return Collection|ComponentGroup[]
     */
    public function getComponentGroups()
    {
        return $this->componentGroups;
    }

    /**
     * @return static
     */
    public function setComponentGroups(iterable $componentGroups)
    {
        $this->componentGroups = new ArrayCollection();
        foreach ($componentGroups as $componentGroup) {
            $this->addComponentGroup($componentGroup);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function addComponentGroup(ComponentGroup $componentGroup)
    {
        if (!$this->componentGroups->contains($componentGroup)) {
            $this->componentGroups->add($componentGroup);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function removeComponentGroup(ComponentGroup $componentGroup)
    {
        if ($this->componentGroups->contains($componentGroup)) {
            $this->componentGroups->remove($componentGroup);
        }

        return $this;
    }
}
