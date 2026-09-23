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

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
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
use Silverback\ApiComponentsBundle\ApiPlatform\Parameter\BooleanQueryValue;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
#[ORM\Table(name: 'page')]
#[ORM\AssociationOverrides([
    new ORM\AssociationOverride(name: 'route', inversedBy: 'page'),
])]
#[ApiResource(
    mercure: true,
    order: ['createdAt' => 'DESC'],
    parameters: [
        'search' => new QueryParameter(filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())), properties: ['title', 'reference', 'uiComponent']),
        'order[:property]' => new QueryParameter(filter: new SortFilter(), properties: ['createdAt', 'reference']),
        'isTemplate' => new QueryParameter(filter: new ExactFilter(), castToNativeType: true, castFn: [BooleanQueryValue::class, 'cast']),
    ],
)]
class Page extends AbstractPage
{
    use UiTrait;

    #[ORM\ManyToOne(targetEntity: Layout::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(name: 'layout_id', onDelete: 'SET NULL', nullable: true)]
    #[Assert\NotBlank(message: 'Please specify a layout.')]
    #[Groups(['Route:manifest:read'])]
    public ?Layout $layout;

    #[ORM\Column(unique: true, nullable: true)]
    #[Assert\NotBlank(message: 'Please enter a reference.')]
    public ?string $reference = null;

    #[ORM\Column(name: 'is_template')]
    #[Assert\NotNull(message: 'Please specify if this page is a template or not.')]
    public bool $isTemplate;

    #[ORM\ManyToMany(targetEntity: ComponentGroup::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[Groups(['Route:manifest:read'])]
    private Collection $componentGroups;

    #[ApiProperty(writable: false)]
    public function getComponentGroups(): Collection
    {
        return $this->componentGroups;
    }

    public function __construct()
    {
        $this->componentGroups = new ArrayCollection();
        $this->initComponentGroups();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('uiComponent', new Assert\NotBlank(
            message: 'Please specify a UI component.',
        ));
    }
}
