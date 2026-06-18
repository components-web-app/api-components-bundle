<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Validator\Constraints as AcbAssert;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
#[ORM\Table(name: 'component_position')]
#[Silverback\Timestamped]
#[ApiResource(mercure: true, order: ['sortValue' => 'ASC'])]
#[AcbAssert\ComponentPosition]
#[Assert\Expression(
    '!(this.component == null & this.pageDataProperty == null)',
    message: 'Please specify either a component or pageDataProperty.',
)]
class ComponentPosition
{
    use IdTrait;
    use TimestampedTrait;

    #[ORM\ManyToOne(targetEntity: ComponentGroup::class, inversedBy: 'componentPositions')]
    #[ORM\JoinColumn(name: 'component_group_id', onDelete: 'CASCADE', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['ComponentPosition:read', 'ComponentPosition:write', 'AbstractComponent:cwa_resource:write'])]
    public ?ComponentGroup $componentGroup = null;

    #[ORM\ManyToOne(targetEntity: AbstractComponent::class, inversedBy: 'componentPositions')]
    #[ORM\JoinColumn(name: 'component_id', onDelete: 'SET NULL', nullable: true)]
    #[Groups(['ComponentPosition:read', 'ComponentPosition:write', 'Route:manifest:read'])]
    public ?AbstractComponent $component = null;

    #[ORM\Column(name: 'page_data_property', nullable: true)]
    #[Groups(['ComponentPosition:read:role_admin', 'ComponentPosition:write'])]
    public ?string $pageDataProperty = null;

    #[ORM\Column(name: 'sort_value', type: 'integer')]
    #[Assert\NotNull]
    #[Groups(['ComponentPosition:read', 'ComponentPosition:write', 'AbstractComponent:cwa_resource:write'])]
    public ?int $sortValue = null;

    /**
     * @return ComponentPosition[]|Collection|null
     */
    #[ApiProperty(readable: false)]
    public function getSortCollection(): ?Collection
    {
        return $this->componentGroup ? $this->componentGroup->componentPositions : null;
    }

    public function setComponentGroup(ComponentGroup $componentGroup): self
    {
        $this->componentGroup = $componentGroup;

        return $this;
    }

    public function setComponent(AbstractComponent $component): self
    {
        $this->component = $component;

        return $this;
    }

    public function setSortValue(int $sortValue): self
    {
        $this->sortValue = $sortValue;

        return $this;
    }

    public function getPageDataProperty(): ?string
    {
        return $this->pageDataProperty;
    }

    public function setPageDataProperty(?string $pageDataProperty): self
    {
        $this->pageDataProperty = $pageDataProperty;

        return $this;
    }
}
