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
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
#[ORM\Table(name: 'layout')]
#[Silverback\Timestamped]
#[ApiResource(mercure: true, order: ['createdAt' => 'DESC'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'reference'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(OrSearchFilter::class, properties: ['reference' => 'ipartial', 'uiComponent' => 'ipartial'])]
#[UniqueEntity(fields: ['reference'], message: 'There is already a Layout with that reference.')]
class Layout
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Please enter a reference.')]
    public string $reference;

    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'layout')]
    #[ApiProperty(writable: false)]
    public Collection $pages;

    #[ORM\ManyToMany(targetEntity: ComponentGroup::class, inversedBy: 'layouts')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $componentGroups;

    public function __construct()
    {
        $this->componentGroups = new ArrayCollection();
        $this->initComponentGroups();
        $this->pages = new ArrayCollection();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('uiComponent', new Assert\NotBlank(
            message: 'You must define the uiComponent for this resource.',
        ));
    }
}
