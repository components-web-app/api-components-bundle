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
#[ApiResource(mercure: true, order: ['sortValue' => 'ASC', 'id' => 'ASC'])]
#[AcbAssert\ComponentPosition]
#[Assert\Expression(
    '!(this.component == null & this.pageDataProperty == null)',
    message: 'Please specify either a component or both pageDataProperty and pageDataClass.',
)]
#[Assert\Expression(
    '!(this.pageDataProperty != null & this.pageDataClass == null) && !(this.pageDataProperty == null & this.pageDataClass != null)',
    message: 'pageDataProperty and pageDataClass must both be set or both be null.',
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
    #[Assert\Length(max: 255)]
    public ?string $pageDataProperty = null;

    #[ORM\Column(name: 'page_data_class', nullable: true)]
    #[Groups(['ComponentPosition:read:role_admin', 'ComponentPosition:write'])]
    #[Assert\Length(max: 255)]
    public ?string $pageDataClass = null;

    #[ORM\Column(name: 'sort_value', type: 'integer')]
    #[Groups(['ComponentPosition:read'])]
    public ?int $sortValue = null;

    #[ApiProperty(readable: false)]
    #[Groups(['ComponentPosition:write', 'AbstractComponent:cwa_resource:write'])]
    public ?string $after = null;

    #[ApiProperty(readable: false)]
    #[Groups(['ComponentPosition:write', 'AbstractComponent:cwa_resource:write'])]
    public ?string $before = null;

    private bool $sortValueSent = false;

    public function markSortValueSent(): void
    {
        $this->sortValueSent = true;
    }

    public function wasSortValueSent(): bool
    {
        return $this->sortValueSent;
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
