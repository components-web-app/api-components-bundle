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

use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
#[ORM\Table(name: 'layout')]
#[Silverback\Timestamped]
#[ApiResource(
    normalizationContext: ['groups' => ['Layout:read']],
    denormalizationContext: ['groups' => ['Layout:write']],
    mercure: true,
    order: ['createdAt' => 'DESC'],
    parameters: [
        'search' => new QueryParameter(filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())), properties: ['reference', 'uiComponent']),
        'order[:property]' => new QueryParameter(filter: new SortFilter(), properties: ['createdAt', 'reference']),
    ],
)]
#[UniqueEntity(fields: ['reference'], message: 'There is already a Layout with that reference.')]
class Layout
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Please enter a reference.')]
    #[Groups(['Layout:read', 'Layout:write'])]
    public string $reference;

    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'layout')]
    #[ApiProperty(writable: false)]
    #[Groups(['Layout:read'])]
    public Collection $pages;

    #[ORM\ManyToMany(targetEntity: ComponentGroup::class, inversedBy: 'layouts')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[Groups(['Layout:read', 'Layout:write'])]
    private Collection $componentGroups;

    public function __construct()
    {
        $this->componentGroups = new ArrayCollection();
        $this->initComponentGroups();
        $this->pages = new ArrayCollection();
    }

    #[ApiProperty(readableLink: false, writableLink: false)]
    public function getComponentGroups(): Collection|array
    {
        return $this->componentGroups;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('uiComponent', new Assert\NotBlank(
            message: 'You must define the uiComponent for this resource.',
        ));
    }
}
