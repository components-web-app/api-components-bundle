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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * We must define this as an API resource, otherwise when serializing and the relation is to this class,
 * API Platform does not know that it will be a resource and will make it an object, not an IRI. (same notes as AbstractComponent).
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
#[ORM\Table(name: 'abstract_page_data')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'dtype', type: 'string', length: 255)]
#[ORM\AssociationOverrides([
    new ORM\AssociationOverride(name: 'route', inversedBy: 'pageData'),
])]
#[ORM\AttributeOverrides([
    new ORM\AttributeOverride(name: 'title', column: new ORM\Column(nullable: false)),
])]
#[ApiResource]
#[Get]
abstract class AbstractPageData extends AbstractPage implements PageDataInterface
{
    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'page_id', nullable: false)]
    #[Assert\NotBlank(message: 'Please select a page template')]
    #[Groups(['Route:manifest:read'])]
    public Page $page;
}
