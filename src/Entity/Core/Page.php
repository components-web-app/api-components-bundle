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

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;
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
#[ApiResource(mercure: true, order: ['createdAt' => 'DESC'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'reference'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(OrSearchFilter::class, properties: ['title' => 'ipartial', 'reference' => 'ipartial', 'uiComponent' => 'ipartial', 'layout.reference' => 'ipartial'])]
#[ApiFilter(\ApiPlatform\Doctrine\Orm\Filter\SearchFilter::class, properties: ['isTemplate' => 'exact'])]
class Page extends AbstractPage
{
    use UiTrait;

    #[ORM\ManyToOne(targetEntity: Layout::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(name: 'layout_id', onDelete: 'SET NULL', nullable: true)]
    #[Assert\NotBlank(message: 'Please specify a layout.')]
    #[Groups(['Route:manifest:read'])]
    public ?Layout $layout;

    #[ORM\Column(unique: true)]
    #[Assert\NotBlank(message: 'Please enter a reference.')]
    public string $reference;

    #[ORM\Column(name: 'is_template')]
    #[Assert\NotNull(message: 'Please specify if this page is a template or not.')]
    public bool $isTemplate;

    #[ORM\ManyToMany(targetEntity: ComponentGroup::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[Groups(['Route:manifest:read'])]
    private Collection $componentGroups;

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
